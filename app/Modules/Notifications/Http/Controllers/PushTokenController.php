<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Notifications\Actions\SetPushToken;
use App\Modules\Notifications\Http\Requests\UpdatePushTokenRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Notifications')]
class PushTokenController
{
    use CurrentMember;

    /**
     * Register this device's Expo push token.
     *
     * Stored on the device bound to the request's token; the same push token is removed from any
     * other device. Logging out deletes the device and its token.
     */
    public function update(UpdatePushTokenRequest $request, SetPushToken $setPushToken): Response
    {
        $setPushToken->handle($this->currentUser($request), $request->string('token')->toString(), $request->string('platform')->toString());

        return response()->noContent();
    }

    /**
     * Remove this device's push token.
     */
    public function destroy(Request $request, SetPushToken $setPushToken): Response
    {
        $setPushToken->handle($this->currentUser($request), null);

        return response()->noContent();
    }
}
