<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Invite;

/**
 * An unused 8-character code from the unambiguous alphabet (no 0/O/1/I).
 */
class GenerateInviteCode
{
    public function handle(): string
    {
        do {
            $code = self::random();
        } while (Invite::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * A random code from the invite alphabet (also used for sign-in codes).
     */
    public static function random(): string
    {
        $alphabet = Invite::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < Invite::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
