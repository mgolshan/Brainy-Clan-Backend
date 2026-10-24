<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\RevenueRecorded;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Billing\Support\Money;
use App\Modules\Family\Models\Family;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns one revenue event into the affiliate side of billing (docs/PLAN.md B10), in one
 * transaction with the family row locked:
 *
 * 1. a paid sale sets `families.first_paid_at` (the earliest one wins);
 * 2. a family without a code is attributed from the event — Paddle `custom_data.affiliate_code`
 *    (`web_checkout` / `link`) or a store offer code mapped to a code (`offer_code`) — and a
 *    paid sale locks the attribution;
 * 3. an attributed family adds a commission entry: net × the affiliate's rate while the event is
 *    within `commission_months` of the family's first paid transaction. Refunds and chargebacks
 *    mirror the sale they reverse (same rate; nothing when that sale earned no commission).
 *
 * Idempotent: `source_event_id` is unique, so a retried job adds nothing.
 */
class RecordRevenue
{
    public function handle(RevenueRecorded $event): ?CommissionEntry
    {
        try {
            return DB::transaction(fn (): ?CommissionEntry => $this->record($event));
        } catch (UniqueConstraintViolationException) {
            return CommissionEntry::query()->where('source_event_id', $event->eventId)->first();
        }
    }

    private function record(RevenueRecorded $event): ?CommissionEntry
    {
        /** @var Family|null $family */
        $family = Family::query()->lockForUpdate()->find($event->familyId);

        if ($family === null) {
            return null;
        }

        $occurredAt = Carbon::parse($event->occurredAt)->utc();

        if ($event->isPaidSale() && ($family->first_paid_at === null || $occurredAt->lessThan($family->first_paid_at))) {
            $family->forceFill(['first_paid_at' => $occurredAt])->save();
        }

        $attribution = FamilyAttribution::query()->with('affiliate')->where('family_id', $family->id)->first()
            ?? $this->attributeFromEvent($family, $event);

        if ($attribution === null) {
            return null;
        }

        if ($event->isPaidSale() && $attribution->locked_at === null) {
            $attribution->forceFill(['locked_at' => $occurredAt])->save();
        }

        $existing = CommissionEntry::query()->where('source_event_id', $event->eventId)->first();

        if ($existing !== null || ($event->grossMinor === 0 && $event->netMinor === 0)) {
            return $existing;
        }

        $terms = $this->terms($event, $attribution, $family, $occurredAt);

        if ($terms === null) {
            return null;
        }

        [$rate, $affiliateId, $affiliateCodeId] = $terms;

        return CommissionEntry::query()->create([
            'affiliate_id' => $affiliateId,
            'affiliate_code_id' => $affiliateCodeId,
            'family_id' => $family->id,
            'source' => $event->source,
            'source_event_id' => $event->eventId,
            'source_reference' => $event->reference,
            'kind' => $event->kind,
            'occurred_at' => $occurredAt,
            'currency' => strtoupper($event->currency),
            'gross_minor' => $event->grossMinor,
            'tax_minor' => $event->taxMinor,
            'fee_minor' => $event->feeMinor,
            'net_minor' => $event->netMinor,
            'commission_rate' => $rate,
            'commission_minor' => Money::share($event->netMinor, $rate),
            'net_usd_minor' => $event->netUsdMinor,
            'commission_usd_minor' => $event->netUsdMinor !== null ? Money::share($event->netUsdMinor, $rate) : null,
            'status' => CommissionEntry::STATUS_PENDING,
            'payable_at' => $occurredAt->copy()->addDays((int) config('billing.affiliates.refund_window_days', 30)),
        ]);
    }

    /**
     * Rate, affiliate and code for this event's entry, or null when it earns (or reverses) no
     * commission. A refund / chargeback of a known sale goes to the affiliate that earned the sale,
     * at the same rate (also after staff moved the family to another code).
     *
     * @return array{0: string, 1: int, 2: int|null}|null
     */
    private function terms(RevenueRecorded $event, FamilyAttribution $attribution, Family $family, Carbon $occurredAt): ?array
    {
        $reverses = in_array($event->kind, [RevenueRecorded::KIND_REFUND, RevenueRecorded::KIND_CHARGEBACK], true);

        if ($reverses && $event->reference !== null) {
            $sale = CommissionEntry::query()
                ->where('source', $event->source)
                ->where('source_reference', $event->reference)
                ->where('kind', CommissionEntry::KIND_SALE)
                ->where('family_id', $family->id)
                ->first();

            return $sale !== null ? [$sale->commission_rate, $sale->affiliate_id, $sale->affiliate_code_id] : null;
        }

        $affiliate = $attribution->affiliate;

        if (! $affiliate->earnsCommission() || $family->first_paid_at === null) {
            return null;
        }

        $windowEnds = $family->first_paid_at->copy()->addMonths($affiliate->commission_months);

        return $occurredAt->lessThan($windowEnds)
            ? [$affiliate->commission_rate, $attribution->affiliate_id, $attribution->affiliate_code_id]
            : null;
    }

    private function attributeFromEvent(Family $family, RevenueRecorded $event): ?FamilyAttribution
    {
        $code = null;
        $source = null;

        if ($event->affiliateCode !== null) {
            $code = AffiliateCode::findUsable($event->affiliateCode);
            $source = $event->affiliateSource === FamilyAttribution::SOURCE_LINK ? FamilyAttribution::SOURCE_LINK : FamilyAttribution::SOURCE_WEB_CHECKOUT;
        }

        if ($code === null && $event->offerCode !== null) {
            $code = AffiliateCode::forOfferCode($event->offerCode);
            $source = FamilyAttribution::SOURCE_OFFER_CODE;
        }

        if ($code === null || $source === null) {
            return null;
        }

        $attribution = FamilyAttribution::query()->create([
            'family_id' => $family->id,
            'affiliate_code_id' => $code->id,
            'affiliate_id' => $code->affiliate_id,
            'source' => $source,
            'attributed_at' => now(),
        ]);

        return $attribution->setRelation('affiliate', $code->affiliate);
    }
}
