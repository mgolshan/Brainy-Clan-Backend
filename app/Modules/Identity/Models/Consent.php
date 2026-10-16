<?php

namespace App\Modules\Identity\Models;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recorded legal consent (append-only): a user accepting the terms or the privacy policy, or an
 * admin giving parental consent for a child invite (`subject_invite_id`, then `subject_member_id`
 * once the child joined). `ip_hash` is an HMAC of the request IP, never the IP itself.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $version
 * @property int|null $subject_member_id
 * @property int|null $subject_invite_id
 * @property Carbon $accepted_at
 * @property string|null $ip_hash
 * @property-read User $user
 * @property-read FamilyMember|null $subjectMember
 * @property-read Invite|null $subjectInvite
 */
#[Fillable(['user_id', 'type', 'version', 'subject_member_id', 'subject_invite_id', 'accepted_at', 'ip_hash'])]
#[UseFactory(ConsentFactory::class)]
class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    public const string TERMS = 'terms';

    public const string PRIVACY = 'privacy';

    public const string PARENTAL = 'parental';

    public const array TYPES = [self::TERMS, self::PRIVACY, self::PARENTAL];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function subjectMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'subject_member_id');
    }

    /**
     * @return BelongsTo<Invite, $this>
     */
    public function subjectInvite(): BelongsTo
    {
        return $this->belongsTo(Invite::class, 'subject_invite_id');
    }
}
