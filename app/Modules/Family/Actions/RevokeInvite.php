<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Invite;

class RevokeInvite
{
    public function handle(Invite $invite): void
    {
        $invite->delete();
    }
}
