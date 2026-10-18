<?php

namespace App\Modules\Content\Models;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Core\Contracts\InvalidatesReferenceCache;
use App\Modules\Core\Support\ReferenceDataBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
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
#[UseEloquentBuilder(ReferenceDataBuilder::class)]
class ChallengeType extends Model implements InvalidatesReferenceCache
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Called after every Eloquent write (ReferenceDataBuilder).
     */
    public static function flushReferenceCaches(): void
    {
        ChallengeTypeRegistry::flush();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('content');
    }
}
