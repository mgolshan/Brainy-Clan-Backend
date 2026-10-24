<?php

namespace App\Modules\ChallengeTypes\Support;

use App\Modules\Identity\Models\User;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Art keys the brain games may use (the app ships the art): the Clan **kid** avatars (the
 * species keys of User::AVATARS, without the -teen/-mom/… variants), the spot-the-difference
 * props and the named palette colors. Also the seeded random source every brain game uses.
 */
final class ClanArt
{
    /** Spot-the-difference prop kinds (docs/API.md "Brain games"). */
    public const array PROPS = ['tree', 'sun', 'cloud', 'flower', 'house', 'ball', 'star', 'kite', 'boat', 'fish', 'bird', 'rock'];

    /** Props whose mirror image looks different, so a `flip` change is visible. */
    public const array ASYMMETRIC_PROPS = ['kite', 'boat', 'fish', 'bird'];

    /** Named palette colors. */
    public const array COLORS = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'brown', 'white'];

    /**
     * The kid avatar keys ("fox", "owl", …), in User::AVATARS order.
     *
     * @return list<string>
     */
    public static function kidAvatars(): array
    {
        return array_values(array_filter(User::AVATARS, fn (string $key) => ! str_contains($key, '-')));
    }

    /**
     * A deterministic random source for one generated item.
     */
    public static function random(string $type, string $seed, int $difficulty): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', "{$type}|{$difficulty}|{$seed}", true)));
    }
}
