<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Family\Models\FamilyMember;
use Illuminate\Support\Collection;

/**
 * Push texts follow each recipient's app language (`users.locale`, en/fr; anything else falls back
 * to English). Listeners keep their texts as `['en' => …, 'fr' => …]` constants and send once per
 * locale group, so a mixed-language family gets each push in its own language. Needs `user` loaded.
 */
class PushLocale
{
    public const array SUPPORTED = ['en', 'fr'];

    public const string FALLBACK = 'en';

    public static function of(FamilyMember $member): string
    {
        $locale = $member->user?->locale;

        return in_array($locale, self::SUPPORTED, true) ? $locale : self::FALLBACK;
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     * @return Collection<string, Collection<int, FamilyMember>>
     */
    public static function group(Collection $members): Collection
    {
        return $members->groupBy(fn (FamilyMember $member) => self::of($member))->map(fn (Collection $group) => $group->values());
    }
}
