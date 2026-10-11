<?php

namespace App\Modules\Goals\Models;

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\Family;
use Database\Factories\GoalPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A family goal: competition (members ranked) or cooperation (team total), per day/week/month.
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property string $mode
 * @property string $period_type
 * @property int $target_points
 * @property string|null $reward_text
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property-read Family $family
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, GoalPlanMember> $planMembers
 * @property-read Collection<int, GoalPeriod> $periods
 */
#[Fillable(['family_id', 'name', 'mode', 'period_type', 'target_points', 'reward_text', 'starts_on', 'ends_on', 'is_active'])]
#[UseFactory(GoalPlanFactory::class)]
class GoalPlan extends Model
{
    /** @use HasFactory<GoalPlanFactory> */
    use HasFactory;

    public const string MODE_COMPETITION = 'competition';

    public const string MODE_COOPERATION = 'cooperation';

    public const array MODES = [self::MODE_COMPETITION, self::MODE_COOPERATION];

    public const string PERIOD_DAILY = 'daily';

    public const string PERIOD_WEEKLY = 'weekly';

    public const string PERIOD_MONTHLY = 'monthly';

    public const array PERIOD_TYPES = [self::PERIOD_DAILY, self::PERIOD_WEEKLY, self::PERIOD_MONTHLY];

    protected function casts(): array
    {
        return [
            'target_points' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Scope categories (each includes its subtree).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'goal_plan_scopes');
    }

    /**
     * @return HasMany<GoalPlanMember, $this>
     */
    public function planMembers(): HasMany
    {
        return $this->hasMany(GoalPlanMember::class);
    }

    /**
     * @return HasMany<GoalPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(GoalPeriod::class);
    }
}
