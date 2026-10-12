<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Modules\Content\Actions\QueueContentBatch;
use App\Modules\Content\Generation\ContentBatchAgent;
use App\Modules\Content\Generation\GenerationTypes;
use App\Modules\Content\Jobs\GenerateContentBatchJob;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Identity\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * AI batch generator: pick category, type, difficulty and age range, locale and count (≤ 50),
 * optionally add a note; the batch is generated on Horizon (`ai` queue) and its items land as
 * drafts. The table lists recent batches; "Review" opens AiBatchReview for approve / edit /
 * reject. Without a provider API key the page shows "AI generation is not configured".
 *
 * @property-read Schema $form
 */
class AiBatchGenerator extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.ai-batch-generator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'AI batch generator';

    protected static ?string $slug = 'ai-batch-generator';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'generation_type' => 'multiple_choice',
            'locale' => 'en',
            'min_difficulty' => 2,
            'max_difficulty' => 5,
            'min_age' => 8,
            'max_age' => null,
            'count' => 20,
        ]);
    }

    public function isConfigured(): bool
    {
        return ContentBatchAgent::isConfigured();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->disabled(fn () => ! $this->isConfigured())
            ->components([
                Select::make('category_id')->label('Category')->options(fn () => CategoryResource::parentOptions())
                    ->searchable()->required()->columnSpan(2)
                    ->helperText('The prompt uses the full path (World Geography → Capitals → Europe).'),
                Select::make('generation_type')->label('Type')->options(fn () => app(GenerationTypes::class)->options())->required()->columnSpan(2),
                TextInput::make('min_difficulty')->label('Difficulty from')->numeric()->minValue(1)->maxValue(10)->required(),
                TextInput::make('max_difficulty')->label('Difficulty to')->numeric()->minValue(1)->maxValue(10)->required()->gte('min_difficulty'),
                TextInput::make('min_age')->label('Min age')->numeric()->minValue(0)->maxValue(120),
                TextInput::make('max_age')->label('Max age')->numeric()->minValue(0)->maxValue(120)->placeholder('no limit'),
                Select::make('locale')->options(['en' => 'English', 'fr' => 'French'])->required(),
                TextInput::make('count')->label('Items')->numeric()->minValue(1)->maxValue((int) config('content_generator.max_items', 50))->required()
                    ->helperText('Up to '.config('content_generator.max_items', 50).' per batch.'),
                Textarea::make('note')->label('Note for the AI (optional)')->rows(2)->maxLength(1000)->columnSpan(2)
                    ->placeholder('e.g. focus on rivers and mountains; avoid capitals'),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();

        try {
            $batch = app(QueueContentBatch::class)->handle($data, $user instanceof User ? $user : null);
        } catch (ValidationException $e) {
            Notification::make()->title('Batch not started')->body(implode(' ', $e->validator->errors()->all()))->danger()->send();

            return;
        }

        Notification::make()
            ->title("Batch #{$batch->id} queued")
            ->body('It runs in the background (about 1–3 minutes). Review it from the table below when its status is "review".')
            ->success()
            ->send();

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ContentBatch::query()
                ->with(['creator'])
                ->withCount(['items', 'draftItems']))
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('category_id')->label('Category')->wrap()
                    ->formatStateUsing(fn (?int $state) => $state !== null ? implode(' → ', app(CategoryTree::class)->pathNames($state)) : '—'),
                TextColumn::make('type_key')->label('Type')->badge()->state(fn (ContentBatch $record) => $record->typeLabel()),
                TextColumn::make('locale'),
                TextColumn::make('status')->badge()->color(fn (string $state) => ContentBatch::statusColor($state))
                    ->tooltip(fn (ContentBatch $record) => $record->error),
                TextColumn::make('requested_count')->label('Asked')->placeholder('—'),
                TextColumn::make('items_count')->label('Stored'),
                TextColumn::make('draft_items_count')->label('To review'),
                TextColumn::make('skipped')->label('Dropped / dup.')
                    ->state(fn (ContentBatch $record) => count($record->skipped['invalid'] ?? []).' / '.count($record->skipped['duplicates'] ?? [])),
                TextColumn::make('output_tokens')->label('Tokens in / out')->toggleable()
                    ->state(fn (ContentBatch $record) => $record->input_tokens !== null ? number_format($record->input_tokens).' / '.number_format((int) $record->output_tokens) : '—'),
                TextColumn::make('creator.display_name')->label('Created by')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(ContentBatch::STATUSES, ContentBatch::STATUSES)),
            ])
            ->recordActions([
                Action::make('review')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (ContentBatch $record) => AiBatchReview::getUrl(['batch' => $record->id]))
                    ->visible(fn (ContentBatch $record) => $record->items_count > 0),
                Action::make('retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Runs the same request again (a new model call).')
                    ->visible(fn (ContentBatch $record) => $record->status === ContentBatch::STATUS_FAILED && $this->isConfigured())
                    ->action(function (ContentBatch $record): void {
                        $record->forceFill(['status' => ContentBatch::STATUS_QUEUED, 'error' => null])->save();
                        GenerateContentBatchJob::dispatch($record->id);
                        Notification::make()->title("Batch #{$record->id} queued again")->success()->send();
                    }),
            ]);
    }
}
