<?php

namespace App\Modules\Policy\Models;

use App\Modules\Core\Contracts\InvalidatesReferenceCache;
use App\Modules\Core\Support\ReferenceDataBuilder;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One entry of the theme world catalog (docs/API.md "Worlds and unlocks"): display order and how
 * the world unlocks. Reference data edited in the admin and cached through ReferenceCache.
 *
 * `required` is the threshold for `points` (lifetime points) and `streak` (longest streak in
 * days); it is null for `free` and `family_plan` (cleared on save). Inactive worlds are left out
 * of the catalog and cannot be picked.
 *
 * @property int $id
 * @property string $key
 * @property int $sort
 * @property string $unlock_type
 * @property int|null $required
 * @property bool $active
 */
#[Fillable(['key', 'sort', 'unlock_type', 'required', 'active'])]
#[UseEloquentBuilder(ReferenceDataBuilder::class)]
class ThemeWorld extends Model implements InvalidatesReferenceCache
{
    use LogsActivity;

    public const string UNLOCK_FREE = 'free';

    public const string UNLOCK_POINTS = 'points';

    public const string UNLOCK_STREAK = 'streak';

    public const string UNLOCK_FAMILY_PLAN = 'family_plan';

    public const array UNLOCK_TYPES = [self::UNLOCK_FREE, self::UNLOCK_POINTS, self::UNLOCK_STREAK, self::UNLOCK_FAMILY_PLAN];

    /** Unlock types that need a `required` threshold. */
    public const array THRESHOLD_TYPES = [self::UNLOCK_POINTS, self::UNLOCK_STREAK];

    /**
     * The seeded catalog (docs/API.md), in display order: key => [unlock type, required].
     *
     * @var array<string, array{0: string, 1: int|null}>
     */
    public const array DEFAULT_CATALOG = [
        'playful' => [self::UNLOCK_FREE, null],
        'sleek' => [self::UNLOCK_FREE, null],
        'modern' => [self::UNLOCK_FREE, null],
        'classic' => [self::UNLOCK_FREE, null],
        'jungle' => [self::UNLOCK_POINTS, 50],
        'sticker' => [self::UNLOCK_POINTS, 100],
        'space' => [self::UNLOCK_POINTS, 150],
        'nature' => [self::UNLOCK_POINTS, 200],
        'sea' => [self::UNLOCK_STREAK, 3],
        'lofi' => [self::UNLOCK_STREAK, 7],
        'dino' => [self::UNLOCK_FAMILY_PLAN, null],
        'neon' => [self::UNLOCK_FAMILY_PLAN, null],
        'midnight' => [self::UNLOCK_FAMILY_PLAN, null],
    ];

    /**
     * Rows for the default catalog (`sort` in steps of 10), for the seeder and the migration.
     *
     * @return list<array{key: string, sort: int, unlock_type: string, required: int|null, active: bool}>
     */
    public static function defaultRows(): array
    {
        $rows = [];
        $sort = 0;

        foreach (self::DEFAULT_CATALOG as $key => [$type, $required]) {
            $sort += 10;
            $rows[] = ['key' => $key, 'sort' => $sort, 'unlock_type' => $type, 'required' => $required, 'active' => true];
        }

        return $rows;
    }

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'required' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ThemeWorld $world): void {
            if (! in_array($world->unlock_type, self::THRESHOLD_TYPES, true)) {
                $world->required = null;
            }
        });
    }

    /**
     * Called after every Eloquent write (ReferenceDataBuilder).
     */
    public static function flushReferenceCaches(): void
    {
        ThemeWorldCatalog::flushCache();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('admin');
    }
}
