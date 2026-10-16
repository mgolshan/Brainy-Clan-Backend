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
        $accessibility = ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => false];

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
                    'accessibility_defaults' => $accessibility,
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
                    'accessibility_defaults' => ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => true],
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
            ['tier', 'free', 'limits.max_categories', 3, 'Free tier: first 3 top-level categories unlocked.'],
            ['tier', 'free', 'limits.max_active_goals', 1, 'Free tier: one active goal plan.'],
            ['tier', 'free', 'second_chance.free_per_day', 0, 'Free second chances per member per day.'],
            ['tier', 'free', 'ads.enabled', true, 'Rewarded ads allowed on this tier (age band must allow too).'],

            ['tier', 'family', 'limits.max_members', 7, 'Family plan: admin + 6 members.'],
            ['tier', 'family', 'limits.max_categories', 100, 'Family plan: all categories.'],
            ['tier', 'family', 'limits.max_active_goals', null, 'Family plan: unlimited goal plans (null).'],
            ['tier', 'family', 'second_chance.free_per_day', 3, 'Free second chances per member per day.'],
            ['tier', 'family', 'ads.enabled', false, 'Paying families never see ads.'],
        ];
    }
}
