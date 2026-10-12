<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\SetPassword;
use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Http\Requests\SetPasswordRequest;
use App\Modules\Identity\Http\Requests\UpdateMeRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Identity')]
class MeController
{
    /**
     * The signed-in user.
     *
     * User, active membership, family and resolved policy. The app calls it on launch.
     */
    public function show(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        return new MeResource($user);
    }

    /**
     * Update my profile.
     *
     * All fields optional: `display_name`, `avatar` (fixed set of keys), `locale`.
     */
    public function update(UpdateMeRequest $request, UpdateProfile $update): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{display_name?: string, avatar?: string, locale?: string} $data */
        $data = $request->validated();

        return new UserResource($update->handle($user, $data));
    }

    /**
     * Set or change my password.
     *
     * For the email login an admin added. `current_password` is required when a password is
     * already set. 422 when the user has no email login.
     */
    public function password(SetPasswordRequest $request, SetPassword $setPassword): Response
    {
        /** @var User $user */
        $user = $request->user();
        $current = $request->input('current_password');

        $setPassword->handle($user, (string) $request->string('password'), is_string($current) ? $current : null);

        return response()->noContent();
    }
}
