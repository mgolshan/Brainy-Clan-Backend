<?php

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Support\ChatText;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A family chat message (docs/API.md "Family chat"). `body` is plain text (normalized by
 * {@see ChatText}), stored encrypted with APP_KEY (`encrypted` cast) and
 * null once deleted (`deleted_at` set: a tombstone, not a soft delete — the app shows
 * "Message deleted"). Never logged: no activity log, no Filament resource, no Sentry data.
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $family_member_id
 * @property string|null $body
 * @property string $client_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property bool|null $reported `withExists('reports as reported')`, loaded for admin viewers only
 * @property-read FamilyMember|null $author
 * @property-read Family $family
 */
#[Fillable(['family_id', 'family_member_id', 'body', 'client_id', 'deleted_at'])]
#[UseFactory(ChatMessageFactory::class)]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public const ?string UPDATED_AT = null;

    /**
     * Never serialize the body by accident (e.g. a model dumped into a log or exception context).
     *
     * @var list<string>
     */
    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Reports by members to the family's admins (deleted with the message, FK cascade).
     *
     * @return HasMany<ChatMessageReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ChatMessageReport::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
