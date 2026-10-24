<?php

namespace App\Modules\Family\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's membership in a family: role, nickname, difficulty override, accessibility.
 *
 * @property int $id
 * @property int $family_id
 * @property int $user_id
 * @property string $role
 * @property string $nickname
 * @property int|null $difficulty_override
 * @property array<string, mixed>|null $accessibility
 * @property string $status
 * @property Carbon|null $joined_at
 * @property bool $chat_enabled Family chat on for this member (a family admin turns it off)
 * @property int|null $chat_last_read_id Family chat read marker (only moves forward)
 * @property-read Family $family
 * @property-read User $user
 */
#[Fillable(['family_id', 'user_id', 'role', 'nickname', 'difficulty_override', 'accessibility', 'chat_enabled', 'status', 'joined_at'])]
#[UseFactory(FamilyMemberFactory::class)]
class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use HasFactory;

    public const string ROLE_ADMIN = 'admin';

    public const string ROLE_MEMBER = 'member';

    public const array ROLES = [self::ROLE_ADMIN, self::ROLE_MEMBER];

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_REMOVED = 'removed';

    /**
     * Matches the column default, so a just-created member has it without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'chat_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'difficulty_override' => 'integer',
            'accessibility' => 'array',
            'joined_at' => 'datetime',
            'chat_enabled' => 'boolean',
            'chat_last_read_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->family->owner_user_id === $this->user_id;
    }
}
