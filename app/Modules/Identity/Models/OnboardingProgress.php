<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's onboarding (FTUE) progress (docs/API.md "Onboarding (FTUE)"): one row per user, so a
 * second device never repeats it. `steps` maps the keys that are done to their first `done_at`
 * (ISO 8601 UTC); keys not in the map are not done. Erased with the account, part of `/me/export`.
 *
 * @property int $id
 * @property int $user_id
 * @property array<string, string> $steps
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'steps', 'dismissed_at'])]
class OnboardingProgress extends Model
{
    protected $table = 'onboarding_progress';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'steps' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
