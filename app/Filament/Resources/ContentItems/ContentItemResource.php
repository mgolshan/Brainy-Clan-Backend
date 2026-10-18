<?php

namespace App\Filament\Resources\ContentItems;

use App\Filament\Imports\ContentItemImporter;
use App\Filament\Pages\ContentPreview;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Support\JsonEditor;
use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Actions\ChangeContentStatus;
use App\Modules\Content\Actions\TagContentItems;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Identity\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Content items: filter by category (incl. sub-categories), type, locale, difficulty, age, status
 * and source; bulk publish / archive / retag; CSV import; open an item in the content preview.
 * Payload and answer are validated by the item's challenge type plugin.
 */
class ContentItemResource extends Resource
{
    protected static ?string $model = ContentItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $types = collect(app(ChallengeTypeRegistry::class)->handlers())
            ->mapWithKeys(fn ($class, $key) => [$key => $key])
            ->all();

        return $schema->components([
            Section::make('Item')->columns(4)->schema([
                Select::make('category_id')->label('Category')->options(fn () => CategoryResource::parentOptions())->searchable()->required()->columnSpan(2),
                Select::make('type_key')->label('Type')->options($types)->required()->live(),
                Select::make('locale')->options(['en' => 'en', 'fr' => 'fr'])->default('en')->required(),
                TextInput::make('difficulty')->numeric()->minValue(1)->maxValue(10)->default(3)->required(),
                TextInput::make('min_age')->numeric()->minValue(0)->maxValue(120),
                TextInput::make('max_age')->numeric()->minValue(0)->maxValue(120),
                Select::make('status')->options(array_combine(ContentItem::STATUSES, ContentItem::STATUSES))->default(ContentItem::STATUS_DRAFT)->required(),
                Select::make('source')->options(array_combine(ContentItem::SOURCES, ContentItem::SOURCES))->default('manual')->required(),
                TextInput::make('translation_group')->maxLength(64)->disabledOn('edit')->dehydrated(fn (string $operation) => $operation === 'create')
                    ->helperText('Translations of one item share this key (seed items: e.g. q:flags-001).'),
                Toggle::make('cross_locale')->label('Offer to other languages')->default(true)->inline(false)
                    ->helperText('Off for language-bound English items (spelling, idioms, words).'),
                Select::make('tags')->relationship('tags', 'name')->multiple()->preload()->createOptionForm([
                    TextInput::make('name')->required()->maxLength(60),
                ])->columnSpan(3),
            ]),
            Section::make('Payload (sent to the app)')->schema([
                JsonEditor::make('payload')->required()->helperText('multiple_choice: {"question", "choices": [{"id","text"}], "image_url"} · wisdom_card: {"card": {"text","author","source"}, "question", "choices"} · word_puzzle: {"variant": "anagram", "letters", "hint"} | {"variant": "missing_letter", "pattern", "hint"} | {"variant": "word_search", "grid", "words"} · sudoku: {"size", "box", "grid"} · math_sprint: {"question", "answer_format", "time_limit_ms"} (Sudoku and Math Sprint are normally generated per session, see the category generators)'),
            ]),
            Section::make('Answer (stays on the server)')->schema([
                JsonEditor::make('answer')->required()
                    ->helperText('choice types: {"choice_id": "a", "explanation": "..." | null} · word_puzzle: {"words": [..]} (anagram) | {"word"} (missing_letter) | {"found": [{"word","start","end"}]} (word_search) · sudoku: {"solution"} · math_sprint: {"value", "allow_decimal"}')
                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $registry = app(ChallengeTypeRegistry::class);
                        $type = (string) $get('type_key');

                        if (! $registry->has($type)) {
                            return;
                        }

                        $payload = json_decode((string) $get('payload'), true);
                        $answer = is_string($value) ? json_decode($value, true) : $value;

                        foreach ($registry->get($type)->validate(is_array($payload) ? $payload : [], is_array($answer) ? $answer : []) as $field => $messages) {
                            foreach ($messages as $message) {
                                $fail("{$field}: {$message}");
                            }
                        }
                    }]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'tags']))
            ->headerActions([
                ImportAction::make()->importer(ContentItemImporter::class)->label('Import CSV'),
            ])
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('category.slug')->label('Category')->formatStateUsing(fn (ContentItem $record) => CategoryResource::path($record->category))->wrap(),
                TextColumn::make('type_key')->label('Type')->badge(),
                TextColumn::make('locale')->badge()->color('gray'),
                TextColumn::make('summary')->label('Question / card')->state(fn (ContentItem $record) => self::summary($record))->limit(70)->wrap(),
                TextColumn::make('difficulty')->sortable(),
                TextColumn::make('min_age')->label('Ages')->state(fn (ContentItem $record) => ($record->min_age ?? 0).'–'.($record->max_age ?? '∞'))->toggleable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    ContentItem::STATUS_PUBLISHED => 'success',
                    ContentItem::STATUS_REVIEW => 'warning',
                    ContentItem::STATUS_ARCHIVED => 'gray',
                    default => 'info',
                }),
                TextColumn::make('source')->toggleable(),
                TextColumn::make('tags.name')->badge()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Category (incl. sub-categories)')
                    ->options(fn () => CategoryResource::parentOptions())->searchable()
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('category_id', app(CategoryTree::class)->subtreeIds((int) $data['value']))
                        : $query),
                SelectFilter::make('type_key')->label('Type')->options(fn () => collect(app(ChallengeTypeRegistry::class)->handlers())->mapWithKeys(fn ($c, $k) => [$k => $k])->all()),
                SelectFilter::make('locale')->options(['en' => 'en', 'fr' => 'fr']),
                TernaryFilter::make('cross_locale')->label('Offered to other languages'),
                SelectFilter::make('status')->options(array_combine(ContentItem::STATUSES, ContentItem::STATUSES)),
                SelectFilter::make('source')->options(array_combine(ContentItem::SOURCES, ContentItem::SOURCES)),
                SelectFilter::make('difficulty')->multiple()->options(array_combine(range(1, 10), array_map('strval', range(1, 10)))),
                SelectFilter::make('age')->label('Suitable for age')->options(array_combine(range(5, 18), array_map('strval', range(5, 18))))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? ContentItem::whereSuitableForAge($query, (int) $data['value']) : $query),
                SelectFilter::make('tags')->relationship('tags', 'name')->multiple()->preload(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ContentItem $record) => ContentPreview::getUrl(['item' => $record->id])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::statusBulkAction('publish', ContentItem::STATUS_PUBLISHED, Heroicon::OutlinedCheckCircle),
                    self::statusBulkAction('send_to_review', ContentItem::STATUS_REVIEW, Heroicon::OutlinedClock),
                    self::statusBulkAction('archive', ContentItem::STATUS_ARCHIVED, Heroicon::OutlinedArchiveBox),
                    BulkAction::make('retag')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            TagsInput::make('tags')->required(),
                            Toggle::make('replace')->label('Replace existing tags'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            /** @var Collection<int, ContentItem> $records */
                            app(TagContentItems::class)->handle($records, array_values((array) $data['tags']), (bool) ($data['replace'] ?? false));
                            Notification::make()->title('Tags updated')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function statusBulkAction(string $name, string $status, Heroicon $icon): BulkAction
    {
        return BulkAction::make($name)
            ->icon($icon)
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($status): void {
                /** @var Collection<int, ContentItem> $records */
                $user = Auth::user();
                $result = app(ChangeContentStatus::class)->handle($records, $status, $user instanceof User ? $user : null);

                $notification = Notification::make()->title("{$result['changed']} item(s) set to {$status}");

                if ($result['skipped'] !== []) {
                    $notification->body('Skipped (invalid payload): #'.implode(', #', $result['skipped']))->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function summary(ContentItem $item): string
    {
        $payload = $item->payload;

        return match (true) {
            isset($payload['question']) => (string) $payload['question'].(isset($payload['card']['text']) ? ' — “'.$payload['card']['text'].'”' : ''),
            ($payload['variant'] ?? null) === 'anagram' => 'Anagram: '.implode('', (array) ($payload['letters'] ?? [])),
            ($payload['variant'] ?? null) === 'missing_letter' => 'Missing letter: '.($payload['pattern'] ?? ''),
            ($payload['variant'] ?? null) === 'word_search' => 'Word search: '.implode(', ', (array) ($payload['words'] ?? [])),
            isset($payload['size']) => 'Sudoku '.$payload['size'].'×'.$payload['size'],
            default => '',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentItems::route('/'),
            'create' => CreateContentItem::route('/create'),
            'edit' => EditContentItem::route('/{record}/edit'),
        ];
    }
}
