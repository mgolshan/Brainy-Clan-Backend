<?php

use App\Filament\Pages\MonthlyPayouts;
use App\Filament\Resources\AffiliateCodes\Pages\ManageAffiliateCodes;
use App\Filament\Resources\Affiliates\Pages\ManageAffiliates;
use App\Filament\Resources\CommissionEntries\Pages\ListCommissionEntries;
use App\Filament\Resources\FamilyAttributions\Pages\ListFamilyAttributions;
use App\Modules\Billing\Actions\CommissionPayoutReport;
use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\CommissionPayout;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin (Billing group, B10): affiliates, codes, attributions, the commission ledger and the
 * monthly payout report — rendered without N+1, staff-only, and payouts / attribution changes logged.
 */

/**
 * `$count` affiliates, each with a code, an attributed family and payable + pending entries.
 */
function affiliateRows(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $affiliate = Affiliate::factory()->create(['name' => "Affiliate {$i}", 'payout_method_note' => "Interac {$i}"]);
        $code = AffiliateCode::factory()->create(['affiliate_id' => $affiliate->id, 'store_offer_codes' => ["OFFER{$i}"]]);
        $owner = familyOwner();
        $staff = User::factory()->staff()->create();
        FamilyAttribution::query()->create([
            'family_id' => $owner->family_id, 'affiliate_code_id' => $code->id, 'affiliate_id' => $affiliate->id,
            'source' => 'code', 'attributed_at' => now(), 'changed_by_user_id' => $staff->id, 'change_reason' => 'test',
        ]);
        CommissionEntry::factory()->create(['affiliate_id' => $affiliate->id, 'affiliate_code_id' => $code->id, 'family_id' => $owner->family_id, 'occurred_at' => '2026-09-05 10:00:00']);
        CommissionEntry::factory()->create(['affiliate_id' => $affiliate->id, 'affiliate_code_id' => $code->id, 'family_id' => $owner->family_id, 'occurred_at' => '2026-09-20 10:00:00', 'currency' => 'CAD', 'commission_usd_minor' => null]);
        CommissionEntry::factory()->create(['affiliate_id' => $affiliate->id, 'affiliate_code_id' => $code->id, 'family_id' => $owner->family_id, 'status' => CommissionEntry::STATUS_PENDING, 'occurred_at' => now()]);
    }
}

function billingAdminQueries(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->actingAs($this->staff);
});

it('renders every Billing affiliate page with rows', function (string $url, string $see) {
    affiliateRows(3);

    $this->get($url)->assertOk()->assertSee($see);
})->with([
    'affiliates' => ['/admin/affiliates', 'Affiliate 2'],
    'codes' => ['/admin/affiliate-codes', 'OFFER1'],
    'attributions' => ['/admin/family-attributions', 'Affiliate 1'],
    'commission entries' => ['/admin/commission-entries', '11.75'],
    'monthly payouts' => ['/admin/monthly-payouts', 'Interac 0'],
]);

it('keeps the affiliate tables at a constant number of queries', function (string $url) {
    affiliateRows(2);
    $few = billingAdminQueries($url);

    affiliateRows(5);
    expect(billingAdminQueries($url))->toBe($few);
})->with([
    '/admin/affiliates',
    '/admin/affiliate-codes',
    '/admin/family-attributions',
    '/admin/commission-entries',
    '/admin/monthly-payouts',
]);

it('creates affiliates and codes (stored upper case)', function () {
    Livewire::test(ManageAffiliates::class)
        ->callAction('create', ['name' => 'Maya', 'contact_email' => 'maya@example.com', 'commission_rate' => '0.3', 'commission_months' => 6, 'status' => 'active'])
        ->assertHasNoActionErrors();

    $affiliate = Affiliate::query()->sole();
    expect($affiliate->commission_rate)->toBe('0.3000');

    Livewire::test(ManageAffiliateCodes::class)
        ->callAction('create', ['code' => 'maya-10', 'affiliate_id' => $affiliate->id, 'store_offer_codes' => ['FOUNDING'], 'paddle_discount_id' => 'dsc_1', 'active' => true])
        ->assertHasNoActionErrors();

    expect(AffiliateCode::query()->sole())->code->toBe('MAYA-10')->store_offer_codes->toBe(['FOUNDING']);

    Livewire::test(ManageAffiliateCodes::class)
        ->callAction('create', ['code' => 'bad code!', 'affiliate_id' => $affiliate->id])
        ->assertHasActionErrors(['code']);
});

it('lists the ledger read-only with filters', function () {
    affiliateRows(2);
    $pending = CommissionEntry::query()->where('status', 'pending')->get();

    Livewire::test(ListCommissionEntries::class)
        ->assertCanSeeTableRecords(CommissionEntry::query()->get())
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords($pending)
        ->assertCanNotSeeTableRecords(CommissionEntry::query()->where('status', 'payable')->get());

    $this->get('/admin/commission-entries/create')->assertNotFound();
});

it('changes an attribution only with a reason and logs it', function () {
    affiliateRows(1);
    $attribution = FamilyAttribution::query()->sole();
    $newCode = AffiliateCode::factory()->create(['code' => 'NEW']);

    Livewire::test(ListFamilyAttributions::class)
        ->callTableAction('changeCode', $attribution, ['affiliate_code_id' => $newCode->id, 'reason' => ''])
        ->assertHasTableActionErrors(['reason']);

    Livewire::test(ListFamilyAttributions::class)
        ->callTableAction('changeCode', $attribution, ['affiliate_code_id' => $newCode->id, 'reason' => 'Parent emailed support #123'])
        ->assertHasNoTableActionErrors();

    expect($attribution->fresh())
        ->affiliate_code_id->toBe($newCode->id)
        ->affiliate_id->toBe($newCode->affiliate_id)
        ->changed_by_user_id->toBe($this->staff->id)
        ->change_reason->toBe('Parent emailed support #123');

    $log = Activity::query()->where('log_name', 'affiliates')->where('description', 'Family attribution changed by staff')->sole();
    expect($log->causer_id)->toBe($this->staff->id)
        ->and($log->properties['reason'])->toBe('Parent emailed support #123');
});

it('reports payable totals per affiliate, month and currency, without family data, as CSV', function () {
    affiliateRows(2);
    $report = app(CommissionPayoutReport::class);

    expect($report->payable())->toHaveCount(4);

    $csv = $report->csv();
    $lines = array_values(array_filter(explode("\n", $csv)));
    expect($lines[0])->toBe('affiliate_id,affiliate,month,currency,entries,net,commission,commission_usd')
        ->and($lines)->toHaveCount(5)
        ->and($csv)->toContain('"Affiliate 0",2026-09,USD,1,46.99,11.75,11.75')
        ->and($csv)->toContain('"Affiliate 0",2026-09,CAD,1,46.99,11.75,')
        ->and($csv)->not->toContain('family');

    Livewire::test(MonthlyPayouts::class)->callTableAction('exportCsv')->assertFileDownloaded('affiliate-payouts-'.now()->format('Y-m-d').'.csv');
});

it('marks a month paid, records the payout and logs it', function () {
    affiliateRows(1);
    $affiliate = Affiliate::query()->sole();
    $key = CommissionPayoutReport::key($affiliate->id, '2026-09', 'USD');

    Livewire::test(MonthlyPayouts::class)
        ->callTableAction('markPaid', $key, ['note' => 'Interac ref 42'])
        ->assertHasNoTableActionErrors();

    $payout = CommissionPayout::query()->sole();
    expect($payout)
        ->affiliate_id->toBe($affiliate->id)
        ->month->toBe('2026-09')
        ->currency->toBe('USD')
        ->amount_minor->toBe(1175)
        ->entries_count->toBe(1)
        ->paid_by_user_id->toBe($this->staff->id)
        ->note->toBe('Interac ref 42');

    expect(CommissionEntry::query()->where('status', 'paid')->sole()->payout_id)->toBe($payout->id)
        ->and(CommissionEntry::query()->where('status', 'payable')->sole()->currency)->toBe('CAD')
        ->and(CommissionEntry::query()->where('status', 'pending')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'Commission payout marked paid')->sole()->causer_id)->toBe($this->staff->id)
        ->and(app(CommissionPayoutReport::class)->payable())->toHaveCount(1);
});

it('keeps payouts and attribution changes away from the support role and everyone else', function () {
    affiliateRows(1);
    $this->actingAs(User::factory()->support()->create());

    $this->get('/admin/monthly-payouts')->assertForbidden();
    $this->get('/admin/affiliates')->assertOk();
    $this->get('/admin/family-attributions')->assertOk()->assertDontSee('Change code');

    $this->actingAs(User::factory()->aged(40)->create());
    foreach (['/admin/affiliates', '/admin/affiliate-codes', '/admin/family-attributions', '/admin/commission-entries', '/admin/monthly-payouts'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('shows the web plan and referral code on the family page', function () {
    affiliateRows(1);
    $attribution = FamilyAttribution::query()->with('affiliateCode')->sole();
    Subscription::factory()->paddle()->create(['family_id' => $attribution->family_id, 'external_id' => 'sub_shown']);

    $this->get("/admin/families/{$attribution->family_id}")->assertOk()
        ->assertSee('sub_shown')
        ->assertSee('paddle')
        ->assertSee($attribution->affiliateCode->code);
});
