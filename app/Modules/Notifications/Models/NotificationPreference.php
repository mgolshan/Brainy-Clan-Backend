<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Support\NotificationDefaults;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's stored notification preferences. Members without a row get the defaults
 * from {@see NotificationDefaults}.
 *
 * @property int $id
 * @property int $family_member_id
 * @property array<string, bool> $types
 * @property string|null $quiet_from HH:MM:SS
 * @property string|null $quiet_to HH:MM:SS
 * @property string $reminder_time HH:MM:SS
 */
#[Fillable(['family_member_id', 'types', 'quiet_from', 'quiet_to', 'reminder_time'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return [
            'types' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }
}
