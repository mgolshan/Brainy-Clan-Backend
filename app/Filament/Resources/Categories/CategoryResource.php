<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Support\CategoryTree;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                ToggleColumn::make('is_active')->label('Active'),
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
