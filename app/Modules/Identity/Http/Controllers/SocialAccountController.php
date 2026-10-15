<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Identity\Actions\LinkSocialAccount;
use App\Modules\Identity\Actions\UnlinkSocialAccount;
use App\Modules\Identity\Http\Requests\LinkSocialAccountRequest;
use App\Modules\Identity\Http\Resources\SocialAccountResource;
use App\Modules\Identity\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Identity')]
class SocialAccountController
{
    /**
     * List my linked Google/Facebook/Apple accounts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return SocialAccountResource::collection($user->socialAccounts()->orderBy('provider')->get());
    }

    /**
     * Link a Google/Facebook/Apple account.
     *
     * Adults only (403 `forbidden` otherwise). Send the provider token like for sign-in (Google:
     * ID token, Facebook: access token, Apple: identity token + optional `nonce`). 409 `social_account_taken` when that account is linked
     * to another user; 401 `social_token_invalid`. Linking another account of the same provider
     * replaces the old link.
     *
     * @status 201
     */
    #[ApiErrors('social_token_invalid', 'social_account_taken', 'forbidden')]
    public function store(LinkSocialAccountRequest $request, string $provider, LinkSocialAccount $link): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $account = $link->handle($user, $provider, (string) $request->string('token'), $request->filled('nonce') ? (string) $request->string('nonce') : null);

        return (new SocialAccountResource($account))->response()->setStatusCode(201);
    }

    /**
     * Unlink a Google/Facebook/Apple account.
     *
     * 409 `last_sign_in_method` when the user has no password and no other linked provider;
     * 404 when that provider isn't linked.
     */
    #[ApiErrors('last_sign_in_method', 'not_found')]
    public function destroy(Request $request, string $provider, UnlinkSocialAccount $unlink): Response
    {
        /** @var User $user */
        $user = $request->user();

        $unlink->handle($user, $provider);

        return response()->noContent();
    }
}
