<?php

namespace App\Modules\Content\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI generation run or import, so its items can be reviewed together (generator in B7).
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $type_key
 * @property string|null $prompt
 * @property string|null $model
 * @property int $item_count
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['category_id', 'type_key', 'prompt', 'model', 'item_count', 'status', 'created_by'])]
class ContentBatch extends Model
{
    public const array STATUSES = ['draft', 'generating', 'review', 'approved', 'rejected'];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
