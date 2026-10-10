<?php

namespace App\Modules\Identity\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One signed-in device, bound to the Sanctum token it uses.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $name
 * @property string $platform
 * @property string|null $app_version
 * @property string|null $push_token
 * @property Carbon|null $last_seen_at
 */
#[Fillable(['user_id', 'personal_access_token_id', 'name', 'platform', 'app_version', 'push_token', 'last_seen_at'])]
#[UseFactory(DeviceFactory::class)]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    public const array PLATFORMS = ['ios', 'android', 'web'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
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
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }
}
