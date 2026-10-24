<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReport;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Account deletion (docs/API.md "Account deletion"): the one place that erases users — `DELETE /me`,
 * `DELETE /family` (children + optionally the owner) and removing a child member.
 *
 * Per user: every Sanctum token and device row (push tokens) goes; social accounts are unlinked
 * (Apple refresh tokens revoked with Apple after the commit); personal data is erased (display name
 * "Deleted player", email, password, birth data, avatar, staff role) and `erased_at` set. The user
 * row itself stays because the append-only points ledger references its memberships; their
 * nicknames become "Deleted player" and accessibility settings are cleared. Unused sign-in codes are
 * voided, notification preferences and family chat messages deleted, the invite they joined with and the unused invites they
 * created (names + birth data of invitees) deleted. Membership status / `MemberRemoved` are the
 * caller's job. Subscriptions stay with the family. A fixed number of queries for any number of users.
 * The activity log gets one entry with user ids only.
 */
class EraseUser
{
    public const string ERASED_NAME = 'Deleted player';

    public function __construct(private readonly RevokeAppleTokens $revokeAppleTokens) {}

    public function handle(User $user, string $reason = 'account_deleted'): void
    {
        $this->handleMany(collect([$user]), $reason);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function handleMany(Collection $users, string $reason = 'account_deleted'): void
    {
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($userIds === []) {
            return;
        }

        $appleAccounts = DB::transaction(function () use ($userIds, $reason): Collection {
            $appleAccounts = SocialAccount::query()
                ->whereIn('user_id', $userIds)
                ->where('provider', SocialAccount::APPLE)
                ->whereNotNull('refresh_token')
                ->get();

            DB::table('devices')->whereIn('user_id', $userIds)->delete();
            PersonalAccessToken::query()
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            SocialAccount::query()->whereIn('user_id', $userIds)->delete();

            $memberIds = FamilyMember::query()->whereIn('user_id', $userIds)->pluck('id')->all();

            if ($memberIds !== []) {
                FamilyMember::query()->whereIn('id', $memberIds)->update([
                    'nickname' => self::ERASED_NAME,
                    'accessibility' => null,
                    'updated_at' => now(),
                ]);
                SignInCode::query()
                    ->whereIn('member_id', $memberIds)
                    ->whereNull('used_at')
                    ->whereNull('voided_at')
                    ->update(['voided_at' => now()]);
                NotificationPreference::query()->whereIn('family_member_id', $memberIds)->delete();
                // Family chat: the user's messages go for good, in every family they were in (their
                // reports with them, FK cascade), and so do the reports they made.
                ChatMessage::query()->whereIn('family_member_id', $memberIds)->delete();
                ChatMessageReport::query()->whereIn('reporter_member_id', $memberIds)->delete();
            }

            Invite::query()
                ->where(fn ($query) => $query
                    ->whereIn('used_by', $userIds)
                    ->orWhere(fn ($query) => $query->whereIn('created_by', $userIds)->whereNull('used_at')))
                ->delete();

            $emails = User::query()->whereIn('id', $userIds)->whereNotNull('email')->pluck('email')->all();

            if ($emails !== []) {
                DB::table((string) config('auth.passwords.users.table', 'password_reset_tokens'))->whereIn('email', $emails)->delete();
            }

            User::query()->whereIn('id', $userIds)->update([
                'display_name' => self::ERASED_NAME,
                'email' => null,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'birth_year' => null,
                'birth_month' => null,
                'avatar' => null,
                'staff_role' => null,
                'erased_at' => now(),
                'updated_at' => now(),
            ]);

            activity('account')
                ->event('erased')
                ->withProperties(['user_ids' => $userIds, 'reason' => $reason])
                ->log('Accounts erased');

            return $appleAccounts;
        });

        // Outside (or after) the transaction: an HTTP call to Apple must not hold row locks, and
        // nothing is revoked when the erasure rolls back.
        DB::afterCommit(fn () => $this->revokeAppleTokens->handle($appleAccounts));
    }
}
