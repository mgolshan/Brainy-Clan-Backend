<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\ReadOnlyForSupport;
use App\Filament\Support\StaffAccess;
use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Support\CategoryTree;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Category tree editor. Filter by parent to see one level, then drag rows to reorder
 * (sort). Each node has per-locale names, an icon, an age range and an active flag.
 */
class CategoryResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')->columns(2)->schema([
                TextInput::make('name.en')->label('Name (English)')->required()->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, ?Category $record) => $record === null ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('name.fr')->label('Name (French)')->maxLength(80),
                TextInput::make('slug')->required()->maxLength(80)->unique(ignoreRecord: true)->alphaDash(),
                Select::make('parent_id')->label('Parent')->options(fn () => self::parentOptions())->searchable()->placeholder('— top level —'),
                Select::make('icon')->options(array_combine(Category::ICONS, Category::ICONS))->helperText('Key the app maps to an icon'),
                TextInput::make('sort')->numeric()->default(0),
            ]),
            Section::make('Visibility')->columns(3)->schema([
                TextInput::make('min_age')->numeric()->minValue(0)->maxValue(120),
                TextInput::make('max_age')->numeric()->minValue(0)->maxValue(120),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('Generated items')
                ->description('Sudoku and Math Sprint items are generated per session from these generators (sessions in this category or a parent include them). The difficulty range says which members it suits.')
                ->collapsible()
                ->schema([
                    Repeater::make('generators')->hiddenLabel()->defaultItems(0)->columns(4)->schema([
                        Select::make('type')->options(fn () => self::generatorTypes())->required(),
                        KeyValue::make('options')->helperText('sudoku: size = 4 | 6 | 9 · math_sprint: mode = times_tables | mental | fractions · brain games (memory_match, pattern_sequence, nonogram, spot_difference): no options, the difficulty sets the size')
                            ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $registry = app(ChallengeTypeRegistry::class);
                                $type = (string) $get('type');
                                $handler = $registry->has($type) ? $registry->get($type) : null;

                                if ($handler instanceof GeneratesItems) {
                                    foreach ($handler->validateOptions(is_array($value) ? $value : []) as $messages) {
                                        foreach ($messages as $message) {
                                            $fail($message);
                                        }
                                    }
                                }
                            }]),
                        TextInput::make('min_difficulty')->numeric()->integer()->minValue(1)->maxValue(10)->default(1)->required(),
                        TextInput::make('max_difficulty')->numeric()->integer()->minValue(1)->maxValue(10)->default(10)->required(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('contentItems'))
            ->columns([
                TextColumn::make('path')->label('Category')
                    ->state(fn (Category $record) => self::path($record))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('slug', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")),
                TextColumn::make('slug')->fontFamily('mono')->toggleable(),
                TextColumn::make('icon')->badge()->toggleable(),
                TextColumn::make('min_age')->label('Ages')->state(fn (Category $record) => ($record->min_age ?? 0).'–'.($record->max_age ?? '∞')),
                TextColumn::make('content_items_count')->label('Items')->sortable(),
                TextColumn::make('generators')->label('Generated')->badge()->toggleable()
                    ->state(fn (Category $record) => array_map(fn ($spec) => $spec->type.($spec->options !== [] ? ' '.implode('/', array_map('strval', $spec->options)) : ''), $record->generatorSpecs())),
                ToggleColumn::make('is_active')->label('Active')->disabled(fn (): bool => ! StaffAccess::isAdmin()),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label('Parent')
                    ->options(fn () => ['' => '— top level —'] + self::parentOptions())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null => $query,
                        '' => $query->whereNull('parent_id'),
                        default => $query->where('parent_id', $data['value']),
                    }),
            ])
            ->recordActions([EditAction::make()]);
    }

    /**
     * Active challenge types that generate items (Sudoku, Math Sprint).
     *
     * @return array<string, string>
     */
    public static function generatorTypes(): array
    {
        $registry = app(ChallengeTypeRegistry::class);

        return collect($registry->handlers())
            ->filter(fn (string $class) => is_a($class, GeneratesItems::class, true))
            ->mapWithKeys(fn (string $class, string $key) => [$key => $registry->get($key)->name()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function parentOptions(): array
    {
        return app(CategoryTree::class)->all()
            ->mapWithKeys(fn (Category $category) => [$category->id => self::path($category)])
            ->sort()
            ->all();
    }

    public static function path(Category $category): string
    {
        $tree = app(CategoryTree::class);
        $names = [$category->localizedName()];
        $parentId = $category->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard++ < 20) {
            $parent = $tree->find($parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($names, $parent->localizedName());
            $parentId = $parent->parent_id;
        }

        return implode(' → ', $names);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
