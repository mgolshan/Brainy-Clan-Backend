<?php

namespace Database\Seeders;

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Actions\OpenGoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Scoring\Actions\RecordDailyActivity;
use App\Modules\Scoring\Models\PointEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Two weeks of points for "The Demos" (so Home, the leaderboard and goals show data) and one
 * weekly cooperation goal "Pizza Friday". Ledger rows use reason `demo_seed` and are only
 * written once (re-running the seeder appends nothing).
 */
class DemoActivitySeeder extends Seeder
{
    public const string REASON = 'demo_seed';

    public function run(): void
    {
        $family = Family::query()->where('name', 'The Demos')->first();

        if ($family === null) {
            return;
        }

        /** @var list<int> $categoryIds */
        $categoryIds = ContentItem::query()->where('status', ContentItem::STATUS_PUBLISHED)->distinct()->pluck('category_id')->map(fn ($id) => (int) $id)->all();

        if ($categoryIds === []) {
            return;
        }

        $this->seedLedger($family, $categoryIds);
        $this->seedGoal($family, $categoryIds);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function seedLedger(Family $family, array $categoryIds): void
    {
        if (PointEntry::query()->where('family_id', $family->id)->where('reason', self::REASON)->exists()) {
            return;
        }

        mt_srand(42);
        $today = CarbonImmutable::now($family->timezone)->startOfDay();
        $activity = app(RecordDailyActivity::class);

        /** @var array<string, array{0: int, 1: int}> $profile active-day chance in %, max points per day */
        $profile = ['Mom' => [60, 6], 'Dad' => [45, 8], 'Sam' => [85, 10], 'Alex' => [70, 9]];

        foreach ($family->members()->get() as $member) {
            /** @var FamilyMember $member */
            [$chance, $max] = $profile[$member->nickname] ?? [50, 5];

            for ($daysAgo = 13; $daysAgo >= 1; $daysAgo--) {
                if (mt_rand(1, 100) > $chance) {
                    continue;
                }

                $day = $today->subDays($daysAgo)->setTime(mt_rand(15, 20), mt_rand(0, 59));
                $points = mt_rand(2, $max);

                for ($i = 0; $i < $points; $i++) {
                    PointEntry::query()->create([
                        'family_id' => $family->id,
                        'member_id' => $member->id,
                        'category_id' => $categoryIds[mt_rand(0, count($categoryIds) - 1)],
                        'amount' => 1,
                        'reason' => self::REASON,
                        'created_at' => $day->addMinutes($i)->utc(),
                    ]);
                }

                $member->setRelation('family', $family);
                $activity->handle($member, $day);
            }
        }
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function seedGoal(Family $family, array $categoryIds): void
    {
        if (GoalPlan::query()->where('family_id', $family->id)->exists()) {
            return;
        }

        $rootIds = Category::query()->whereNull('parent_id')->orderBy('sort')->pluck('id')->all();
        $scope = Category::query()->whereKey($categoryIds)->get()
            ->map(fn (Category $c) => $c->parent_id ?? $c->id)
            ->first(fn ($id) => in_array($id, $rootIds, true)) ?? $categoryIds[0];

        $plan = GoalPlan::query()->create([
            'family_id' => $family->id,
            'name' => 'Pizza Friday',
            'mode' => GoalPlan::MODE_COOPERATION,
            'period_type' => GoalPlan::PERIOD_WEEKLY,
            'target_points' => 100,
            'reward_text' => 'Pizza night!',
            'starts_on' => CarbonImmutable::now($family->timezone)->startOfWeek()->toDateString(),
            'is_active' => true,
        ]);
        $plan->categories()->sync([$scope]);
        $plan->planMembers()->createMany($family->members()->get()->map(fn (FamilyMember $m) => [
            'member_id' => $m->id,
            'target_override' => $m->nickname === 'Sam' ? 20 : null,
        ])->all());
        $plan->setRelation('family', $family);

        app(OpenGoalPeriod::class)->handle($plan);
    }
}
