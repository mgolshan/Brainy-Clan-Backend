<?php

namespace App\Modules\Content\Models;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Admin-side record of a challenge type plugin: maps a `key` (sent to the app as `type`)
 * to its handler class and lets admins switch a type off without a release.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $handler_class
 * @property array<string, mixed>|null $config
 * @property bool $is_active
 */
#[Fillable(['key', 'name', 'handler_class', 'config', 'is_active'])]
class ChallengeType extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ChallengeTypeRegistry::flush());
        static::deleted(fn () => ChallengeTypeRegistry::flush());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('content');
    }
}
