<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Http\Requests\UpdateMeRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

class MeController
{
    public function show(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        return new MeResource($user);
    }

    public function update(UpdateMeRequest $request, UpdateProfile $update): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{display_name?: string, avatar?: string, locale?: string} $data */
        $data = $request->validated();

        return new UserResource($update->handle($user, $data));
    }
}
