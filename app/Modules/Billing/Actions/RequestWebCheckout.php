<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebCheckoutRequest;
use App\Modules\Core\Support\Website;
use App\Modules\Identity\Actions\RecordConsent;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The website subscribe form (`POST /web/subscribe`): stores a request (email only as an HMAC)
 * and emails the address, queued so the page answers equally fast in every case:
 *
 * - an adult admin of a family that can buy now gets a one-time link (30 minutes) to
 *   `/subscribe/checkout?token=…`;
 * - any other address gets an email explaining why not (no account, not an admin, the plan is
 *   managed in the App Store / Google Play, already subscribed on the web).
 *
 * Older pending links for the same email stop working. The page never reveals anything.
 */
class RequestWebCheckout
{
    public function __construct(private readonly CheckWebCheckoutEligibility $eligibility) {}

    public function handle(string $email, string $plan, ?string $affiliateCode, ?string $affiliateSource, string $locale = 'en', ?string $ipAddress = null): WebCheckoutRequest
    {
        $email = strtolower(trim($email));
        $emailHash = WebCheckoutRequest::hashEmail($email);
        $user = User::query()->where('email', $email)->whereNull('erased_at')->first();
        $reason = $this->eligibility->forUser($user);
        $member = $user?->activeMembership();
        $token = $reason === null ? Str::random(64) : null;

        $request = DB::transaction(function () use ($emailHash, $user, $member, $reason, $token, $plan, $affiliateCode, $affiliateSource, $locale, $ipAddress): WebCheckoutRequest {
            WebCheckoutRequest::query()
                ->where('email_hash', $emailHash)
                ->whereIn('status', [WebCheckoutRequest::STATUS_PENDING, WebCheckoutRequest::STATUS_OPENED])
                ->update(['status' => WebCheckoutRequest::STATUS_SUPERSEDED, 'token_hash' => null, 'updated_at' => now()]);

            return WebCheckoutRequest::query()->create([
                'email_hash' => $emailHash,
                'user_id' => $user?->id,
                'family_id' => $member?->family_id,
                'plan' => $plan,
                'affiliate_code' => $affiliateCode,
                'affiliate_source' => $affiliateCode !== null ? $affiliateSource : null,
                'token_hash' => $token !== null ? WebCheckoutRequest::hashToken($token) : null,
                'status' => match (true) {
                    $reason === null => WebCheckoutRequest::STATUS_PENDING,
                    $user === null => WebCheckoutRequest::STATUS_NO_ACCOUNT,
                    default => WebCheckoutRequest::STATUS_INELIGIBLE,
                },
                'reason' => $reason,
                'locale' => $locale,
                'expires_at' => now()->addMinutes((int) config('billing.web_checkout.link_ttl_minutes', 30)),
                'ip_hash' => RecordConsent::hashIp($ipAddress),
            ]);
        });

        if ($token !== null) {
            $url = Website::route('website.subscribe.checkout', ['token' => $token, ...($locale === 'en' ? [] : ['lang' => $locale])]);
            Mail::to($email)->queue(new WebCheckoutMail($locale, $plan, $url, null, null));
        } else {
            $store = $reason === CheckWebCheckoutEligibility::STORE_PLAN_ACTIVE && $member !== null
                ? $this->eligibility->activePlan($member->family_id)?->store
                : null;

            Mail::to($email)->queue(new WebCheckoutMail($locale, $plan, null, $reason, in_array($store, ['app_store', 'play_store'], true) ? $store : null));
        }

        return $request;
    }

    /**
     * Whether the store of an active plan is one the email can name (for the "managed in" text).
     */
    public static function storeLabel(?string $store, string $locale): string
    {
        return match ($store) {
            'app_store' => 'App Store',
            'play_store' => 'Google Play',
            Subscription::STORE_PADDLE => $locale === 'fr' ? 'le Web' : 'the web',
            default => $locale === 'fr' ? 'la boutique d’applications' : 'your app store',
        };
    }
}
