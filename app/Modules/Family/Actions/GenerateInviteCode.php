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
        $alphabet = Invite::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        do {
            $code = '';

            for ($i = 0; $i < Invite::CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while (Invite::query()->where('code', $code)->exists());

        return $code;
    }
}
