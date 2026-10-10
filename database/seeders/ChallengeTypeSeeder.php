<?php

namespace Database\Seeders;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\ChallengeType;
use Illuminate\Database\Seeder;

/**
 * One `challenge_types` row per plugin in config/challenge_types.php.
 */
class ChallengeTypeSeeder extends Seeder
{
    public function run(ChallengeTypeRegistry $registry): void
    {
        foreach ((array) config('challenge_types.handlers') as $key => $class) {
            $handler = $registry->get((string) $key);

            ChallengeType::query()->updateOrCreate(['key' => $key], [
                'name' => $handler->name(),
                'handler_class' => $class,
            ]);
        }
    }
}
