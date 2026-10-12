<?php

namespace App\Modules\Policy\Models;

use App\Modules\Policy\PolicyResolver;
use Database\Factories\AgeBandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An age range and the rules that apply to it (editable in the admin, never hard-coded).
 *
 * `policy` keys: ui_theme, is_child, ads_allowed, purchases_allowed, collect_email, email_login_allowed,
 * redemption_allowed, difficulty {min, max, default}, accessibility_defaults
 * {text_scale, reduced_motion, calm_timers}, data_rules {...}.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $min_age
 * @property int|null $max_age
 * @property string|null $country_code
 * @property array<string, mixed> $policy
 */
#[Fillable(['code', 'name', 'min_age', 'max_age', 'country_code', 'policy'])]
#[UseFactory(AgeBandFactory::class)]
class AgeBand extends Model
{
    /** @use HasFactory<AgeBandFactory> */
    use HasFactory, LogsActivity;

    public const array THEMES = ['playful', 'sleek', 'modern', 'classic'];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'policy' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PolicyResolver::flushCache());
        static::deleted(fn () => PolicyResolver::flushCache());
    }

    public function contains(int $age): bool
    {
        return $age >= $this->min_age && ($this->max_age === null || $age <= $this->max_age);
    }

    /**
     * Read a key from the policy JSON (dot notation).
     */
    public function rule(string $key, mixed $default = null): mixed
    {
        return data_get($this->policy, $key, $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('admin');
    }
}
