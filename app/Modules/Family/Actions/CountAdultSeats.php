<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Family;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Adult seats in use (docs/API.md "Classroom tools" → "Adult seats"): active adult members +
 * pending adult invites, in two count queries. An adult is anyone at least
 * `PolicyResolver::adultMinAge()` old today; a user without birth data resolves to the adult band
 * (like `PolicyResolver::bandForAge()`).
 */
class CountAdultSeats
{
    public function __construct(private readonly PolicyResolver $policy) {}

    public function handle(Family $family): int
    {
        $minAge = $this->policy->adultMinAge($family->country_code);

        if ($minAge === null) {
            return 0;
        }

        $adultMembers = $family->members()
            ->whereHas('user', fn (Builder $query) => $query->where(
                fn (Builder $query) => $this->bornAtLeastYearsAgo($query->whereNull('birth_year'), $minAge, 'or'),
            ))
            ->count();

        $adultInvites = $this->bornAtLeastYearsAgo($family->pendingInvites()->getQuery(), $minAge)->count();

        return $adultMembers + $adultInvites;
    }

    /**
     * Birth year/month at least `$years` ago this month (the birthday counts as passed within the
     * birth month; a missing month counts as January), i.e. `PolicyResolver::age() >= $years`.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private function bornAtLeastYearsAgo(Builder $query, int $years, string $boolean = 'and'): Builder
    {
        $year = now()->year - $years;
        $month = now()->month;

        return $query->where(fn (Builder $query) => $query
            ->where('birth_year', '<', $year)
            ->orWhere(fn (Builder $query) => $query->where('birth_year', $year)->whereRaw('coalesce(birth_month, 1) <= ?', [$month])), boolean: $boolean);
    }
}
