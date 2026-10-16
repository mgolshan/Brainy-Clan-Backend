<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CheckAccountDeletion;
use App\Modules\Family\Actions\DeleteAccount;
use App\Modules\Family\Actions\DeleteFamily;
use App\Modules\Family\Http\Requests\DeleteAccountRequest;
use App\Modules\Family\Http\Requests\DeleteFamilyRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Identity')]
class AccountDeletionController
{
    use CurrentMember;

    /**
     * Can I delete my account?
     *
     * `blockers`: `family_owner` (owner of a family with other members: delete the family
     * instead), `child_account` (children/teens are removed by an admin). `deletes_family`: the
     * caller owns the family and is its only member, so the family goes too. `active_subscription`
     * + `store`: tell the user to cancel the store subscription (deleting does not cancel billing).
     */
    public function check(Request $request, CheckAccountDeletion $check): JsonResponse
    {
        return new JsonResponse(['data' => $check->handle($this->currentUser($request))]);
    }

    /**
     * Delete my account.
     *
     * Body `confirm: "DELETE"`. Signs out every device, unlinks social accounts (Apple tokens are
     * revoked), leaves the family and erases personal data now. Points ledger rows stay without
     * personal data. 409 `family_owner_cannot_leave` for the owner of a family with other members;
     * 403 `forbidden` for children/teens.
     */
    #[ApiErrors('family_owner_cannot_leave', 'forbidden')]
    public function destroyMe(DeleteAccountRequest $request, DeleteAccount $delete): Response
    {
        $delete->handle($this->currentUser($request));

        return response()->noContent();
    }

    /**
     * Delete the family (owner).
     *
     * Body `confirm: "<family name>"`, `delete_my_account?`. Erases every child account, removes
     * the other adults' memberships (their accounts remain) and erases the owner's account too
     * when `delete_my_account` is true. 403 `forbidden` for anyone but the owner.
     */
    #[ApiErrors('forbidden')]
    public function destroyFamily(DeleteFamilyRequest $request, DeleteFamily $delete): Response
    {
        $member = $this->currentMember($request);

        if (! $member->isOwner()) {
            throw ApiException::forbidden('Only the family owner can delete the family.');
        }

        $delete->handle($member->family, $request->boolean('delete_my_account'));

        return response()->noContent();
    }
}
