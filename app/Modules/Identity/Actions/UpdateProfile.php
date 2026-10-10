<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

class UpdateProfile
{
    /**
     * @param  array{display_name?: string, avatar?: string, locale?: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->fill(array_intersect_key($data, array_flip(['display_name', 'avatar', 'locale'])))->save();

        return $user;
    }
}
