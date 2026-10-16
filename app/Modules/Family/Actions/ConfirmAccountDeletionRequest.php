<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\AccountDeletionRequest;

/**
 * The emailed link of a `/account/delete` request was confirmed: runs the same deletion as
 * `DELETE /me` (`DeleteAccount`) when nothing blocks it. The token works once.
 *
 * Outcomes: `deleted`, `invalid` (unknown, used, superseded or expired link), `family_owner` (owns a
 * family with other members: delete the family in the app or contact support), `child_account`
 * (a family admin removes the account).
 */
class ConfirmAccountDeletionRequest
{
    public const string DELETED = 'deleted';

    public const string INVALID = 'invalid';

    public function __construct(
        private readonly CheckAccountDeletion $check,
        private readonly DeleteAccount $deleteAccount,
    ) {}

    public function handle(string $token): string
    {
        $request = AccountDeletionRequest::query()
            ->with('user')
            ->where('token_hash', AccountDeletionRequest::hashToken($token))
            ->first();

        if ($request === null || ! $request->isUsable() || $request->user === null || $request->user->erased_at !== null) {
            return self::INVALID;
        }

        $user = $request->user;
        $blockers = $this->check->handle($user)['blockers'];

        if ($blockers !== []) {
            $request->forceFill(['status' => AccountDeletionRequest::STATUS_BLOCKED, 'token_hash' => null])->save();

            return in_array(CheckAccountDeletion::BLOCKER_FAMILY_OWNER, $blockers, true)
                ? CheckAccountDeletion::BLOCKER_FAMILY_OWNER
                : CheckAccountDeletion::BLOCKER_CHILD_ACCOUNT;
        }

        $this->deleteAccount->handle($user);

        $request->forceFill([
            'status' => AccountDeletionRequest::STATUS_COMPLETED,
            'token_hash' => null,
            'confirmed_at' => now(),
        ])->save();

        return self::DELETED;
    }
}
