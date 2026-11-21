<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (B12 step 1, docs/API.md "Plans and tiers"): the tier settings of the new
     * `big_clan` and `classroom` plans, and the Family plan's member limit 7 → 6. New rows use
     * insert-or-ignore on the unique key (key, scope, scope_id), so rows an admin already created
     * are kept; the Family limit only changes while it still holds the old default 7. Reference
     * caches are flushed after migrations (MigrationsEnded listener).
     */
    private const array SETTINGS = [
        'big_clan' => [
            'limits.max_members' => [12, 'Big Clan plan: up to 12 members (grandparents, cousins, homeschool groups).'],
            'limits.max_categories' => [100, 'Big Clan plan: all categories.'],
            'limits.max_active_goals' => [null, 'Big Clan plan: unlimited goal plans (null).'],
            'second_chance.free_per_day' => [3, 'Free second chances per member per day.'],
            'ads.enabled' => [false, 'Paying families never see ads.'],
            'chat.retention_days' => [365, 'Big Clan plan: family chat messages are deleted after this many days.'],
        ],
        'classroom' => [
            'limits.max_members' => [37, 'Classroom plan: 1 teacher + 1 co-teacher + 35 students.'],
            'limits.max_categories' => [100, 'Classroom plan: all categories.'],
            'limits.max_active_goals' => [null, 'Classroom plan: unlimited goal plans (null).'],
            'second_chance.free_per_day' => [3, 'Free second chances per member per day.'],
            'ads.enabled' => [false, 'Paying plans never see ads.'],
            'chat.retention_days' => [365, 'Classroom plan: chat messages are deleted after this many days.'],
        ],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (self::SETTINGS as $tier => $settings) {
            foreach ($settings as $key => [$value, $description]) {
                $rows[] = [
                    'key' => $key,
                    'scope' => 'tier',
                    'scope_id' => $tier,
                    'value' => json_encode($value),
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('settings')->insertOrIgnore($rows);

        $family = DB::table('settings')->where('key', 'limits.max_members')->where('scope', 'tier')->where('scope_id', 'family')->first();

        if ($family !== null && json_decode((string) $family->value, true) === 7) {
            DB::table('settings')->where('id', $family->id)->update([
                'value' => json_encode(6),
                'description' => 'Family plan: up to 6 members (parents + kids).',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('scope', 'tier')->whereIn('scope_id', array_keys(self::SETTINGS))->delete();
    }
};
