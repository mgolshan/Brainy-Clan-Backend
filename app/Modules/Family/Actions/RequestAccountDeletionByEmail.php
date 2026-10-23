<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Support\Website;
use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Family\Models\AccountDeletionRequest;
use App\Modules\Identity\Actions\RecordConsent;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Someone asks on `/account/delete` to delete the account with this email. A request row is always
 * stored (email only as an HMAC); when an account has that email, a one-time confirmation link is
 * emailed (queued). Older pending requests for the same email stop working. The caller shows the
 * same answer either way, so the page never reveals whether an email has an account.
 */
class RequestAccountDeletionByEmail
{
    public function handle(string $email, string $locale = 'en', ?string $ipAddress = null): void
    {
        $email = strtolower(trim($email));
        $emailHash = AccountDeletionRequest::hashEmail($email);
        $user = User::query()->where('email', $email)->whereNull('erased_at')->first();
        $token = $user !== null ? Str::random(64) : null;

        DB::transaction(function () use ($emailHash, $user, $token, $ipAddress): void {
            AccountDeletionRequest::query()
                ->where('email_hash', $emailHash)
                ->where('status', AccountDeletionRequest::STATUS_PENDING)
                ->update(['status' => AccountDeletionRequest::STATUS_SUPERSEDED, 'token_hash' => null, 'updated_at' => now()]);

            AccountDeletionRequest::query()->create([
                'email_hash' => $emailHash,
                'user_id' => $user?->id,
                'token_hash' => $token !== null ? AccountDeletionRequest::hashToken($token) : null,
                'status' => $user !== null ? AccountDeletionRequest::STATUS_PENDING : AccountDeletionRequest::STATUS_NO_ACCOUNT,
                'expires_at' => now()->addMinutes(AccountDeletionRequest::TTL_MINUTES),
                'ip_hash' => RecordConsent::hashIp($ipAddress),
            ]);
        });

        if ($user !== null && $token !== null) {
            $url = Website::route('account.delete.confirm', ['token' => $token, ...($locale === 'en' ? [] : ['lang' => $locale])]);
            Mail::to($email)->queue(new AccountDeletionConfirmation($url, $locale));
        }
    }
}
