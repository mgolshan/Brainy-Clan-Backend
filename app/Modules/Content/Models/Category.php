<?php

namespace App\Modules\Content\Models;

use App\Modules\ChallengeTypes\Support\GeneratorSpec;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\Contracts\InvalidatesReferenceCache;
use App\Modules\Core\Support\ReferenceDataBuilder;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A node in the category tree. `name` is per locale ({"en": "...", "fr": "..."}).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property array<string, string> $name
 * @property string|null $icon
 * @property int $sort
 * @property int|null $min_age
 * @property int|null $max_age
 * @property bool $is_active
 * @property list<array<string, mixed>>|null $generators generated challenge types (see GeneratorSpec)
 * @property-read Category|null $parent
 */
#[Fillable(['parent_id', 'slug', 'name', 'icon', 'sort', 'min_age', 'max_age', 'is_active', 'generators'])]
#[UseFactory(CategoryFactory::class)]
#[UseEloquentBuilder(ReferenceDataBuilder::class)]
class Category extends Model implements InvalidatesReferenceCache
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsActivity;

    /** Icon keys the app knows how to render (docs/API.md). */
    public const array ICONS = ['grid', 'puzzle', 'quote', 'globe', 'landmark', 'leaf', 'calculator', 'book'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'sort' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'is_active' => 'boolean',
            'generators' => 'array',
        ];
    }

    /**
     * Called after every Eloquent write (ReferenceDataBuilder), including Filament's
     * reorder (a mass `CASE` update) and seeders.
     */
    public static function flushReferenceCaches(): void
    {
        CategoryTree::flush();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort');
    }

    /**
     * @return HasMany<ContentItem, $this>
     */
    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    /**
     * Name in the given locale, falling back to English, then to the slug.
     */
    public function localizedName(string $locale = 'en'): string
    {
        return $this->name[$locale] ?? $this->name['en'] ?? $this->slug;
    }

    /**
     * The generators this category declares itself (not its subtree).
     *
     * @return list<GeneratorSpec>
     */
    public function generatorSpecs(): array
    {
        $specs = [];

        foreach ((array) ($this->generators ?? []) as $raw) {
            $spec = is_array($raw) ? GeneratorSpec::fromArray($this->id, $raw) : null;

            if ($spec !== null) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    public function allowsAge(?int $age): bool
    {
        if ($age === null) {
            return true;
        }

        return ($this->min_age === null || $age >= $this->min_age) && ($this->max_age === null || $age <= $this->max_age);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('content');
    }
}
