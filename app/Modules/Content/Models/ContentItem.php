<?php

namespace App\Modules\Content\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One playable item. `payload` goes to the app; `answer` stays on the server until the
 * member has answered (the server is the referee).
 *
 * @property int $id
 * @property int $category_id
 * @property string $type_key
 * @property string $locale
 * @property int $difficulty
 * @property int|null $min_age
 * @property int|null $max_age
 * @property array<string, mixed> $payload
 * @property array<string, mixed> $answer
 * @property string $status
 * @property string $source
 * @property string|null $source_ref
 * @property int|null $content_batch_id
 * @property int|null $reviewed_by
 * @property Carbon|null $published_at
 * @property-read Category $category
 */
#[Fillable(['category_id', 'type_key', 'locale', 'difficulty', 'min_age', 'max_age', 'payload', 'answer', 'status', 'source', 'source_ref', 'content_batch_id', 'reviewed_by', 'published_at'])]
#[UseFactory(ContentItemFactory::class)]
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory, LogsActivity;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_REVIEW = 'review';

    public const string STATUS_PUBLISHED = 'published';

    public const string STATUS_ARCHIVED = 'archived';

    public const array STATUSES = [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public const array SOURCES = ['manual', 'seed', 'csv', 'ai', 'generated'];

    protected function casts(): array
    {
        return [
            'difficulty' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'payload' => 'array',
            'answer' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsTo<ContentBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ContentBatch::class, 'content_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<ContentItem>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Items suitable for this age (null age = no age filter).
     *
     * @param  Builder<ContentItem>  $query
     */
    public function scopeForAge(Builder $query, ?int $age): void
    {
        if ($age !== null) {
            self::whereSuitableForAge($query, $age);
        }
    }

    /**
     * @template TBuilder of Builder<ContentItem>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function whereSuitableForAge(Builder $query, int $age): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('min_age')->orWhere('min_age', '<=', $age))
            ->where(fn (Builder $q) => $q->whereNull('max_age')->orWhere('max_age', '>=', $age));
    }

    public function allowsAge(?int $age): bool
    {
        return $age === null || (($this->min_age === null || $age >= $this->min_age) && ($this->max_age === null || $age <= $this->max_age));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'difficulty', 'min_age', 'max_age', 'payload', 'answer', 'category_id'])
            ->logOnlyDirty()->useLogName('content');
    }
}
