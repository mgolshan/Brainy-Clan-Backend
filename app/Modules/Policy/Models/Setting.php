<?php

namespace App\Modules\Policy\Models;

use App\Modules\Policy\PolicyResolver;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A feature flag, limit or remote-config value. Resolution order (most specific wins):
 * age_band > tier > global. `value` is JSON, so it can hold numbers, strings, booleans or objects.
 *
 * @property int $id
 * @property string $key
 * @property string $scope
 * @property string|null $scope_id
 * @property mixed $value
 * @property string|null $description
 */
#[Fillable(['key', 'scope', 'scope_id', 'value', 'description'])]
#[UseFactory(SettingFactory::class)]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsActivity;

    public const string SCOPE_GLOBAL = 'global';

    public const string SCOPE_TIER = 'tier';

    public const string SCOPE_AGE_BAND = 'age_band';

    public const array SCOPES = [self::SCOPE_GLOBAL, self::SCOPE_TIER, self::SCOPE_AGE_BAND];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PolicyResolver::flushCache());
        static::deleted(fn () => PolicyResolver::flushCache());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('admin');
    }
}
