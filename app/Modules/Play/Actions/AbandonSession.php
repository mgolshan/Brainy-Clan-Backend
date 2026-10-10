<?php

namespace App\Modules\Play\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Play\Models\PlaySession;

class AbandonSession
{
    public function handle(PlaySession $session): PlaySession
    {
        if ($session->isFinished()) {
            throw ApiException::sessionNotActive('This session has already ended.');
        }

        $session->forceFill(['status' => PlaySession::STATUS_ABANDONED])->save();

        return $session;
    }
}
