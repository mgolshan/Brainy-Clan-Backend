<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cross-cutting filter for content (holiday, Canada, easy-read …).
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name'])]
class Tag extends Model
{
    /**
     * @return BelongsToMany<ContentItem, $this>
     */
    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class);
    }
}
