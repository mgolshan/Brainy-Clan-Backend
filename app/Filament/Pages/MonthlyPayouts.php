<?php

namespace App\Filament\Pages;

use App\Filament\Support\StaffAccess;
use App\Modules\Billing\Actions\CommissionPayoutReport;
use App\Modules\Billing\Actions\MarkCommissionsPaid;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Monthly affiliate payouts (B10): payable commission (past the 30-day refund window) per
 * affiliate, month and currency. Pay it outside the app (manual transfer), then "Mark paid":
 * the entries move to `paid`, a payout is recorded and the activity log notes who did it.
 * The CSV has counts and amounts only — no family data — so it can be sent to an affiliate.
 */
class MonthlyPayouts extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.monthly-payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 24;

    protected static ?string $navigationLabel = 'Monthly payouts';

    protected static ?string $title = 'Monthly payouts';

    protected static ?string $slug = 'monthly-payouts';

    /**
     * Payouts move money: admins only (hidden and 403 for `support`).
     */
    public static function canAccess(): bool
    {
        return StaffAccess::isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(CommissionPayoutReport::class)->payable()->all())
            ->paginated(false)
            ->emptyStateHeading('Nothing payable')
            ->emptyStateDescription('Commission becomes payable 30 days after the revenue event (refund window).')
            ->columns([
                TextColumn::make('month'),
                TextColumn::make('affiliate')->weight('bold'),
                TextColumn::make('currency'),
                TextColumn::make('entries')->alignEnd(),
                TextColumn::make('net')->label('Net revenue')->alignEnd(),
                TextColumn::make('commission')->label('Commission due')->alignEnd()->weight('bold')
                    ->color(fn (array $record): ?string => $record['commission_minor'] < 0 ? 'danger' : null),
                TextColumn::make('commission_usd')->label('≈ USD')->alignEnd()->placeholder('—'),
                TextColumn::make('payout_method')->label('Payout method')->placeholder('—')->wrap()->toggleable(),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => response()->streamDownload(
                        function (): void {
                            echo app(CommissionPayoutReport::class)->csv();
                        },
                        'affiliate-payouts-'.now()->format('Y-m-d').'.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    )),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (array $record): string => "Record that {$record['affiliate']} was paid {$record['commission']} {$record['currency']} for {$record['month']}? A negative total means refunds to deduct from the next payment.")
                    ->schema([
                        TextInput::make('note')->maxLength(255)->placeholder('e.g. Interac reference'),
                    ])
                    ->action(function (array $record, array $data): void {
                        $staff = StaffAccess::user();
                        abort_unless($staff !== null && StaffAccess::isAdmin(), 403);

                        $payout = app(MarkCommissionsPaid::class)->handle(
                            (int) $record['affiliate_id'],
                            (string) $record['month'],
                            (string) $record['currency'],
                            $staff,
                            is_string($data['note'] ?? null) ? $data['note'] : null,
                        );

                        $payout !== null
                            ? Notification::make()->title('Marked paid')->success()->send()
                            : Notification::make()->title('Nothing left to pay for that month')->warning()->send();
                    }),
            ]);
    }
}
