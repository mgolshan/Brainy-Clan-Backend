<?php

namespace Database\Seeders;

use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Age bands (child 0–12, teen 13–17, adult 18–64, senior 65+) and the default settings.
 * Safe to re-run: rows are matched on their natural keys and updated.
 */
class PolicySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->ageBands() as $band) {
            AgeBand::query()->updateOrCreate(
                ['code' => $band['code'], 'country_code' => null],
                $band,
            );
        }

        foreach ($this->settings() as [$scope, $scopeId, $key, $value, $description]) {
            Setting::query()->updateOrCreate(
                ['key' => $key, 'scope' => $scope, 'scope_id' => $scopeId],
                ['value' => $value, 'description' => $description],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ageBands(): array
    {
        $accessibility = ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => false, 'sound_effects' => false];

        return [
            [
                'code' => 'child', 'name' => 'Child', 'min_age' => 0, 'max_age' => 12,
                'policy' => [
                    'ui_theme' => 'playful',
                    'is_child' => true,
                    'ads_allowed' => false,
                    'purchases_allowed' => false,
                    'collect_email' => false,
                    'email_login_allowed' => false,
                    'redemption_allowed' => true,
                    'ad_request' => ['non_personalized' => true, 'under_age_of_consent' => false, 'max_ad_content_rating' => 'G'],
                    'difficulty' => ['min' => 1, 'max' => 4, 'default' => 2],
                    'accessibility_defaults' => [...$accessibility, 'sound_effects' => true],
                    'data_rules' => ['analytics' => false, 'personalized_ads' => false, 'real_name' => false],
                ],
            ],
            [
                'code' => 'teen', 'name' => 'Teen', 'min_age' => 13, 'max_age' => 17,
                'policy' => [
                    'ui_theme' => 'sleek',
                    'is_child' => false,
                    'ads_allowed' => true,
                    'purchases_allowed' => false,
                    'collect_email' => false,
                    'email_login_allowed' => true,
                    'redemption_allowed' => true,
                    'ad_request' => ['non_personalized' => true, 'under_age_of_consent' => true, 'max_ad_content_rating' => 'G'],
                    'difficulty' => ['min' => 3, 'max' => 8, 'default' => 5],
                    'accessibility_defaults' => $accessibility,
                    'data_rules' => ['analytics' => true, 'personalized_ads' => false, 'real_name' => false],
                ],
            ],
            [
                'code' => 'adult', 'name' => 'Adult', 'min_age' => 18, 'max_age' => 64,
                'policy' => [
                    'ui_theme' => 'modern',
                    'is_child' => false,
                    'ads_allowed' => true,
                    'purchases_allowed' => true,
                    'collect_email' => true,
                    'email_login_allowed' => true,
                    'redemption_allowed' => true,
                    'ad_request' => ['non_personalized' => true, 'under_age_of_consent' => false, 'max_ad_content_rating' => 'G'],
                    'difficulty' => ['min' => 1, 'max' => 10, 'default' => 5],
                    'accessibility_defaults' => $accessibility,
                    'data_rules' => ['analytics' => true, 'personalized_ads' => false, 'real_name' => true],
                ],
            ],
            [
                'code' => 'senior', 'name' => 'Senior', 'min_age' => 65, 'max_age' => null,
                'policy' => [
                    'ui_theme' => 'classic',
                    'is_child' => false,
                    'ads_allowed' => true,
                    'purchases_allowed' => true,
                    'collect_email' => true,
                    'email_login_allowed' => true,
                    'redemption_allowed' => true,
                    'ad_request' => ['non_personalized' => true, 'under_age_of_consent' => false, 'max_ad_content_rating' => 'G'],
                    'difficulty' => ['min' => 1, 'max' => 8, 'default' => 4],
                    'accessibility_defaults' => ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => true, 'sound_effects' => false],
                    'data_rules' => ['analytics' => true, 'personalized_ads' => false, 'real_name' => true],
                ],
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: string, 3: mixed, 4: string}>
     */
    private function settings(): array
    {
        return [
            ['global', null, 'min_app_version', '1.0.0', 'Apps older than this get 426 app_update_required.'],
            ['global', null, 'play.target_points', 10, 'Points needed to complete a play session.'],
            ['global', null, 'play.blunder_cap_pct', 30, 'Mistake allowance as % of the target (30% of 10 = 3).'],
            ['global', null, 'play.redemption_items', 3, 'Easier questions in a redemption round (all must be correct).'],
            ['global', null, 'second_chance.redemption_enabled', true, 'Redemption round available as a second-chance path.'],
            ['global', null, 'invites.ttl_days', 7, 'Days an invite code stays valid.'],
            ['global', null, 'sign_in_codes.ttl_minutes', 15, 'Minutes an admin-issued sign-in code stays valid.'],
            ['global', null, 'feature_flags', [], 'Feature flags sent to the app (merged global <- tier <- age band).'],

            ['tier', 'free', 'limits.max_members', 2, 'Free tier: admin + 1 member.'],
            ['tier', 'free', 'limits.max_adults', null, 'Free tier: no limit on adult members (null); the member limit applies.'],
            ['tier', 'free', 'limits.max_categories', 3, 'Free tier: first 3 top-level categories unlocked.'],
            ['tier', 'free', 'limits.max_active_goals', 1, 'Free tier: one active goal plan.'],
            ['tier', 'free', 'second_chance.free_per_day', 0, 'Free second chances per member per day.'],
            ['tier', 'free', 'ads.enabled', true, 'Rewarded ads allowed on this tier (age band must allow too).'],
            ['tier', 'free', 'chat.retention_days', 7, 'Free tier: family chat messages are deleted after this many days.'],
            ['tier', 'free', 'chat.modes_allowed', false, 'Free tier: chat modes (announcements only, chat off) are a Classroom tool.'],

            ['tier', 'family', 'limits.max_members', 6, 'Family plan: up to 6 members (parents + kids).'],
            ['tier', 'family', 'limits.max_adults', null, 'Family plan: no limit on adult members (null); the member limit applies.'],
            ['tier', 'family', 'limits.max_categories', 100, 'Family plan: all categories.'],
            ['tier', 'family', 'limits.max_active_goals', null, 'Family plan: unlimited goal plans (null).'],
            ['tier', 'family', 'second_chance.free_per_day', 3, 'Free second chances per member per day.'],
            ['tier', 'family', 'ads.enabled', false, 'Paying families never see ads.'],
            ['tier', 'family', 'chat.retention_days', 365, 'Family plan: family chat messages are deleted after this many days.'],
            ['tier', 'family', 'chat.modes_allowed', false, 'Family plan: chat modes (announcements only, chat off) are a Classroom tool.'],

            ['tier', 'big_clan', 'limits.max_members', 12, 'Big Clan plan: up to 12 members (grandparents, cousins, homeschool groups).'],
            ['tier', 'big_clan', 'limits.max_adults', null, 'Big Clan plan: no limit on adult members (null); the member limit applies.'],
            ['tier', 'big_clan', 'limits.max_categories', 100, 'Big Clan plan: all categories.'],
            ['tier', 'big_clan', 'limits.max_active_goals', null, 'Big Clan plan: unlimited goal plans (null).'],
            ['tier', 'big_clan', 'second_chance.free_per_day', 3, 'Free second chances per member per day.'],
            ['tier', 'big_clan', 'ads.enabled', false, 'Paying families never see ads.'],
            ['tier', 'big_clan', 'chat.retention_days', 365, 'Big Clan plan: family chat messages are deleted after this many days.'],
            ['tier', 'big_clan', 'chat.modes_allowed', false, 'Big Clan plan: chat modes (announcements only, chat off) are a Classroom tool.'],

            ['tier', 'classroom', 'limits.max_members', 37, 'Classroom plan: 1 teacher + 1 co-teacher + 35 students.'],
            ['tier', 'classroom', 'limits.max_adults', 2, 'Classroom plan: 2 adult seats (teacher + co-teacher); everyone else is a student.'],
            ['tier', 'classroom', 'limits.max_categories', 100, 'Classroom plan: all categories.'],
            ['tier', 'classroom', 'limits.max_active_goals', null, 'Classroom plan: unlimited goal plans (null).'],
            ['tier', 'classroom', 'second_chance.free_per_day', 3, 'Free second chances per member per day.'],
            ['tier', 'classroom', 'ads.enabled', false, 'Paying plans never see ads.'],
            ['tier', 'classroom', 'chat.retention_days', 365, 'Classroom plan: chat messages are deleted after this many days.'],
            ['tier', 'classroom', 'chat.modes_allowed', true, 'Classroom plan: the teacher can set the chat to announcements only or turn it off.'],
        ];
    }
}
