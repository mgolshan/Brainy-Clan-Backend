<?php

namespace App\Modules\Policy\Models;

use App\Modules\Core\Contracts\InvalidatesReferenceCache;
use App\Modules\Core\Support\ReferenceDataBuilder;
use App\Modules\Policy\PolicyResolver;
use Database\Factories\AgeBandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
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
 * {text_scale, reduced_motion, calm_timers, sound_effects}, data_rules {...}.
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
#[UseEloquentBuilder(ReferenceDataBuilder::class)]
class AgeBand extends Model implements InvalidatesReferenceCache
{
    /** @use HasFactory<AgeBandFactory> */
    use HasFactory, LogsActivity;

    /** Theme keys ("worlds", docs/API.md Member). Any member may pick any world. */
    public const array THEMES = ['playful', 'sleek', 'modern', 'classic', 'jungle', 'space', 'neon'];

    /** Readable names for the admin (the app shows the same names). */
    public const array THEME_LABELS = [
        'playful' => 'Candy Land',
        'sleek' => 'Glass',
        'modern' => 'Modern',
        'classic' => 'Classic',
        'jungle' => 'Jungle Adventure',
        'space' => 'Space Explorers',
        'neon' => 'Neon Arcade',
    ];

    /** AdMob max ad content ratings, strictest first. */
    public const array AD_CONTENT_RATINGS = ['G', 'PG', 'T', 'MA'];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'policy' => 'array',
        ];
    }

    /**
     * Called after every Eloquent write (ReferenceDataBuilder).
     */
    public static function flushReferenceCaches(): void
    {
        PolicyResolver::flushCache();
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
