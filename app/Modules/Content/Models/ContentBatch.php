<?php

namespace App\Modules\Content\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\ContentBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One AI generation run (or a hand-made import batch), so its items can be reviewed together.
 *
 * Status flow of an AI batch: queued → running → review → done, or failed. Hand-made batches
 * stay `draft`.
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $type_key
 * @property string $locale
 * @property string|null $variant word_puzzle variant (anagram, missing_letter, word_search)
 * @property int|null $min_difficulty
 * @property int|null $max_difficulty
 * @property int|null $min_age
 * @property int|null $max_age
 * @property int|null $requested_count
 * @property string|null $note admin note added to the prompt
 * @property string|null $prompt
 * @property string|null $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int $item_count drafts stored
 * @property array{invalid?: list<array{item: int, errors: list<string>}>, duplicates?: list<string>}|null $skipped
 * @property string $status
 * @property string|null $error
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property-read Category|null $category
 * @property-read User|null $creator
 */
#[Fillable([
    'category_id', 'type_key', 'locale', 'variant', 'min_difficulty', 'max_difficulty', 'min_age', 'max_age',
    'requested_count', 'note', 'prompt', 'model', 'input_tokens', 'output_tokens', 'item_count', 'skipped',
    'status', 'error', 'created_by', 'started_at', 'finished_at',
])]
#[UseFactory(ContentBatchFactory::class)]
class ContentBatch extends Model
{
    /** @use HasFactory<ContentBatchFactory> */
    use HasFactory;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_REVIEW = 'review';

    public const string STATUS_DONE = 'done';

    public const string STATUS_FAILED = 'failed';

    public const array STATUSES = [self::STATUS_DRAFT, self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_REVIEW, self::STATUS_DONE, self::STATUS_FAILED];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'min_difficulty' => 'integer',
            'max_difficulty' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'requested_count' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'skipped' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return HasMany<ContentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    /**
     * @return HasMany<ContentItem, $this>
     */
    public function draftItems(): HasMany
    {
        return $this->items()->where('status', ContentItem::STATUS_DRAFT);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Type and variant as one label, e.g. "word_puzzle:anagram". */
    public function typeLabel(): string
    {
        return $this->variant !== null ? "{$this->type_key}:{$this->variant}" : $this->type_key;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_REVIEW => 'warning',
            self::STATUS_DONE => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_RUNNING, self::STATUS_QUEUED => 'info',
            default => 'gray',
        };
    }
}
