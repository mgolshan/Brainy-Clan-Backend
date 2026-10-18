<?php

namespace App\Modules\Policy;

use App\Modules\Core\Support\ReferenceCache;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Contracts\SecondChanceUsage;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single place that answers rule questions ("can this member see ads?", "what difficulty
 * range?", "how many second chances today?") from age band + tier + settings.
 * Actions and controllers ask this class; they never hard-code rules.
 *
 * Bound as a scoped singleton: age bands and settings are loaded once per request/job and
 * cached (ReferenceCache::POLICY) until any AgeBand or Setting row is written.
 */
class PolicyResolver
{
    /** Defaults used when a setting is missing from the database. */
    private const array DEFAULT_SETTINGS = [
        'min_app_version' => '1.0.0',
        'limits.max_members' => 2,
        'limits.max_categories' => 3,
        'limits.max_active_goals' => 1,
        'second_chance.free_per_day' => 0,
        'second_chance.redemption_enabled' => true,
        'ads.enabled' => true,
        'play.target_points' => 10,
        'play.blunder_cap_pct' => 30,
        'play.redemption_items' => 3,
        'invites.ttl_days' => 7,
        'sign_in_codes.ttl_minutes' => 15,
        'feature_flags' => [],
    ];

    private const array DEFAULT_ACCESSIBILITY = ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => false];

    /** @var array{bands: Collection<int, AgeBand>, settings: array<string, mixed>}|null */
    private ?array $snapshot = null;

    public function __construct(private readonly SecondChanceUsage $secondChanceUsage) {}

    /**
     * Called on every AgeBand/Setting write (ReferenceDataBuilder): forgets this request's copy
     * now and invalidates the shared cache once the write is committed.
     */
    public static function flushCache(): void
    {
        ReferenceCache::flush(ReferenceCache::POLICY);

        if (app()->resolved(self::class)) {
            app(self::class)->snapshot = null;
        }
    }

    // ---------------------------------------------------------------------------------
    // Full policy object (docs/API.md "Policy")
    // ---------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $member = $user->activeMembership();

        return $member !== null ? $this->forMember($member) : $this->build($user, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function forMember(FamilyMember $member): array
    {
        return $this->build($member->user, $member->family, $member);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user, ?Family $family, ?FamilyMember $member): array
    {
        $tier = $family !== null ? $family->tier : Family::TIER_FREE;
        $age = $this->age($user->birth_year, $user->birth_month);
        $band = $this->bandForAge($age, $family?->country_code);
        $adsAllowed = $this->adsAllowed($band, $tier);

        return [
            'age_band' => $band->code ?? 'adult',
            'age' => $age,
            'is_child' => $this->isChildBand($band),
            'ui_theme' => (string) ($band?->rule('ui_theme') ?? 'modern'),
            'tier' => $tier,
            'ads_allowed' => $adsAllowed,
            'purchases_allowed' => (bool) ($band?->rule('purchases_allowed') ?? false),
            'collect_email' => $this->collectsEmail($band),
            'email_login_allowed' => $this->emailLoginAllowed($band),
            'difficulty' => $this->difficulty($band, $member?->difficulty_override),
            'second_chance' => [
                'free_per_day' => $this->freeSecondChancesPerDay($tier, $band),
                'free_used_today' => $member !== null ? $this->secondChanceUsage->freeUsedToday($member) : 0,
                'ad_allowed' => $adsAllowed,
                'redemption_allowed' => $this->redemptionAllowed($band, $tier),
            ],
            'ad_request' => $this->adRequest($band),
            'limits' => [
                'max_members' => (int) $this->setting('limits.max_members', $tier, $band?->code),
                'max_categories' => (int) $this->setting('limits.max_categories', $tier, $band?->code),
                'max_active_goals' => $this->maxActiveGoalsFor($tier),
            ],
            'accessibility_defaults' => $this->accessibilityDefaults($band),
            'feature_flags' => (object) $this->featureFlags($tier, $band?->code),
        ];
    }

    // ---------------------------------------------------------------------------------
    // Age and age bands
    // ---------------------------------------------------------------------------------

    /**
     * Age in whole years from birth year + month. Within the birth month the birthday counts
     * as passed (we never store the day).
     */
    public function age(?int $birthYear, ?int $birthMonth, ?CarbonInterface $at = null): ?int
    {
        if ($birthYear === null) {
            return null;
        }

        $at ??= now();
        $age = $at->year - $birthYear;

        if ($at->month < ($birthMonth ?? 1)) {
            $age--;
        }

        return max(0, $age);
    }

    /**
     * The band for an age; country-specific bands win over global ones. Unknown age
     * (e.g. admin staff without birth data) resolves to the `adult` band.
     */
    public function bandForAge(?int $age, ?string $countryCode = null): ?AgeBand
    {
        $bands = $this->snapshot()['bands'];

        if ($age === null) {
            return $bands->firstWhere('code', 'adult') ?? $bands->last();
        }

        if ($countryCode !== null) {
            $countryBand = $bands->first(fn (AgeBand $band) => $band->country_code === $countryCode && $band->contains($age));

            if ($countryBand !== null) {
                return $countryBand;
            }
        }

        return $bands->first(fn (AgeBand $band) => $band->country_code === null && $band->contains($age));
    }

    public function bandForBirth(?int $birthYear, ?int $birthMonth, ?string $countryCode = null): ?AgeBand
    {
        return $this->bandForAge($this->age($birthYear, $birthMonth), $countryCode);
    }

    public function bandForMember(FamilyMember $member): ?AgeBand
    {
        return $this->bandForBirth($member->user->birth_year, $member->user->birth_month, $member->family->country_code);
    }

    public function memberAge(FamilyMember $member): ?int
    {
        return $this->age($member->user->birth_year, $member->user->birth_month);
    }

    public function isChildBand(?AgeBand $band): bool
    {
        return (bool) ($band?->rule('is_child') ?? false);
    }

    public function isChild(FamilyMember $member): bool
    {
        return $this->isChildBand($this->bandForMember($member));
    }

    /**
     * Whether a user is in a child band (with their active family's country bands, if any).
     */
    public function isChildUser(User $user): bool
    {
        $member = $user->activeMembership();
        $member?->loadMissing('family');

        return $this->isChildBand($this->bandForBirth($user->birth_year, $user->birth_month, $member?->family->country_code));
    }

    /**
     * Whether we may collect email/password for someone in this band (adults only by default).
     */
    public function collectsEmail(?AgeBand $band): bool
    {
        return (bool) ($band?->rule('collect_email') ?? false);
    }

    /**
     * Whether an admin may give a member of this band an email login (`email_login_allowed`;
     * seeded: child false, teen/adult/senior true). Falls back to `collect_email` for bands
     * that predate the key.
     */
    public function emailLoginAllowed(?AgeBand $band): bool
    {
        return (bool) ($band?->rule('email_login_allowed') ?? $this->collectsEmail($band));
    }

    public function emailLoginAllowedFor(FamilyMember $member): bool
    {
        return $this->emailLoginAllowed($this->bandForMember($member));
    }

    // ---------------------------------------------------------------------------------
    // Rules used by actions
    // ---------------------------------------------------------------------------------

    public function maxMembers(Family $family): int
    {
        return (int) $this->setting('limits.max_members', $family->tier);
    }

    public function maxCategories(Family $family): int
    {
        return (int) $this->setting('limits.max_categories', $family->tier);
    }

    /**
     * Active goal plans allowed for a tier; null = unlimited.
     */
    public function maxActiveGoalsFor(string $tier): ?int
    {
        $value = $this->setting('limits.max_active_goals', $tier);

        return $value === null ? null : (int) $value;
    }

    public function inviteTtlDays(): int
    {
        return (int) $this->setting('invites.ttl_days');
    }

    /**
     * Minutes an admin-issued sign-in code stays valid.
     */
    public function signInCodeTtlMinutes(): int
    {
        return max(1, (int) $this->setting('sign_in_codes.ttl_minutes'));
    }

    public function minAppVersion(): string
    {
        return (string) $this->setting('min_app_version');
    }

    /**
     * @return array{min: int, max: int, default: int}
     */
    public function difficultyForMember(FamilyMember $member): array
    {
        return $this->difficulty($this->bandForMember($member), $member->difficulty_override);
    }

    /**
     * @return array{target_points: int, blunder_cap: int, redemption_items: int}
     */
    public function playRules(FamilyMember $member): array
    {
        $tier = $member->family->tier;
        $band = $this->bandForMember($member)?->code;
        $target = max(1, (int) $this->setting('play.target_points', $tier, $band));
        $pct = max(0, (int) $this->setting('play.blunder_cap_pct', $tier, $band));

        return [
            'target_points' => $target,
            'blunder_cap' => max(1, (int) ceil($target * $pct / 100)),
            'redemption_items' => max(1, (int) $this->setting('play.redemption_items', $tier, $band)),
        ];
    }

    /**
     * @return array{free_per_day: int, free_used_today: int, free_remaining_today: int, ad_allowed: bool, redemption_allowed: bool}
     */
    public function secondChanceForMember(FamilyMember $member): array
    {
        $band = $this->bandForMember($member);
        $tier = $member->family->tier;
        $perDay = $this->freeSecondChancesPerDay($tier, $band);
        $used = $this->secondChanceUsage->freeUsedToday($member);

        return [
            'free_per_day' => $perDay,
            'free_used_today' => $used,
            'free_remaining_today' => max(0, $perDay - $used),
            'ad_allowed' => $this->adsAllowed($band, $tier),
            'redemption_allowed' => $this->redemptionAllowed($band, $tier),
        ];
    }

    /**
     * Raw setting lookup. Resolution order: age_band > tier > global > built-in default.
     */
    public function setting(string $key, ?string $tier = null, ?string $ageBandCode = null, mixed $default = null): mixed
    {
        $settings = $this->snapshot()['settings'];

        foreach ([
            [Setting::SCOPE_AGE_BAND, $ageBandCode],
            [Setting::SCOPE_TIER, $tier],
            [Setting::SCOPE_GLOBAL, null],
        ] as [$scope, $scopeId]) {
            if ($scope !== Setting::SCOPE_GLOBAL && $scopeId === null) {
                continue;
            }

            $lookup = self::settingKey($scope, $scopeId, $key);

            if (array_key_exists($lookup, $settings)) {
                return $settings[$lookup];
            }
        }

        return $default ?? (self::DEFAULT_SETTINGS[$key] ?? null);
    }

    // ---------------------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------------------

    private function adsAllowed(?AgeBand $band, string $tier): bool
    {
        return (bool) ($band?->rule('ads_allowed') ?? false)
            && (bool) $this->setting('ads.enabled', $tier, $band->code);
    }

    /**
     * How the app must request rewarded ads (AdMob request configuration), from the band policy
     * `ad_request`. Missing values fall back to non-personalized ads rated G; `under_age_of_consent`
     * is set per band (seeded: teen true).
     *
     * @return array{non_personalized: bool, under_age_of_consent: bool, max_ad_content_rating: string}
     */
    public function adRequest(?AgeBand $band): array
    {
        $rating = $band?->rule('ad_request.max_ad_content_rating');

        return [
            'non_personalized' => (bool) ($band?->rule('ad_request.non_personalized') ?? true),
            'under_age_of_consent' => (bool) ($band?->rule('ad_request.under_age_of_consent') ?? false),
            'max_ad_content_rating' => is_string($rating) && in_array($rating, AgeBand::AD_CONTENT_RATINGS, true) ? $rating : 'G',
        ];
    }

    private function redemptionAllowed(?AgeBand $band, string $tier): bool
    {
        return (bool) ($band?->rule('redemption_allowed') ?? true)
            && (bool) $this->setting('second_chance.redemption_enabled', $tier, $band?->code);
    }

    private function freeSecondChancesPerDay(string $tier, ?AgeBand $band): int
    {
        return max(0, (int) $this->setting('second_chance.free_per_day', $tier, $band?->code));
    }

    /**
     * @return array{min: int, max: int, default: int}
     */
    private function difficulty(?AgeBand $band, ?int $override): array
    {
        if ($override !== null) {
            return [
                'min' => max(1, $override - 2),
                'max' => min(10, $override + 2),
                'default' => $override,
            ];
        }

        $min = (int) ($band?->rule('difficulty.min') ?? 1);
        $max = (int) ($band?->rule('difficulty.max') ?? 10);

        return [
            'min' => $min,
            'max' => max($min, $max),
            'default' => (int) ($band?->rule('difficulty.default') ?? intdiv($min + $max, 2)),
        ];
    }

    /**
     * @return array{text_scale: float, reduced_motion: bool, calm_timers: bool}
     */
    private function accessibilityDefaults(?AgeBand $band): array
    {
        $defaults = array_replace(self::DEFAULT_ACCESSIBILITY, (array) ($band?->rule('accessibility_defaults') ?? []));

        return [
            'text_scale' => (float) $defaults['text_scale'],
            'reduced_motion' => (bool) $defaults['reduced_motion'],
            'calm_timers' => (bool) $defaults['calm_timers'],
        ];
    }

    /**
     * Feature flags merged global <- tier <- age band.
     *
     * @return array<string, mixed>
     */
    private function featureFlags(string $tier, ?string $bandCode): array
    {
        $settings = $this->snapshot()['settings'];
        $flags = [];

        foreach ([
            self::settingKey(Setting::SCOPE_GLOBAL, null, 'feature_flags'),
            self::settingKey(Setting::SCOPE_TIER, $tier, 'feature_flags'),
            self::settingKey(Setting::SCOPE_AGE_BAND, $bandCode, 'feature_flags'),
        ] as $lookup) {
            if (isset($settings[$lookup]) && is_array($settings[$lookup])) {
                $flags = array_replace($flags, $settings[$lookup]);
            }
        }

        return $flags;
    }

    private static function settingKey(string $scope, ?string $scopeId, string $key): string
    {
        return $scope.'|'.($scopeId ?? '').'|'.$key;
    }

    /**
     * @return array{bands: Collection<int, AgeBand>, settings: array<string, mixed>}
     */
    private function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        /** @var array{bands: list<array<string, mixed>>, settings: array<string, mixed>} $raw */
        $raw = ReferenceCache::remember(ReferenceCache::POLICY, function (): array {
            $settings = [];

            foreach (Setting::query()->get() as $setting) {
                $settings[self::settingKey($setting->scope, $setting->scope_id, $setting->key)] = $setting->value;
            }

            return [
                'bands' => AgeBand::query()->orderBy('min_age')->get()->map(fn (AgeBand $band) => $band->getAttributes())->all(),
                'settings' => $settings,
            ];
        });

        return $this->snapshot = [
            'bands' => AgeBand::hydrate($raw['bands']),
            'settings' => $raw['settings'],
        ];
    }
}
