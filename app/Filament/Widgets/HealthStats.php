<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AccountDeletionRequests\AccountDeletionRequestResource;
use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use App\Filament\Support\DashboardMetrics;
use App\Filament\Support\StaffAccess;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Family\Models\AccountDeletionRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Failed jobs, webhook errors, AdMob SSV rejections, push readiness and account-deletion requests.
 */
class HealthStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'System health';

    protected function getStats(): array
    {
        $health = app(DashboardMetrics::class)->health();
        $pushShare = $health['devices'] > 0 ? round(100 * $health['devices_with_push'] / $health['devices']) : 0;

        return [
            Stat::make('Failed jobs', number_format($health['failed_jobs']))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description(number_format($health['failed_jobs_day']).' in the last 24 hours')
                ->color($health['failed_jobs_day'] > 0 ? 'danger' : 'success')
                // Horizon is for admins only (it can retry and delete jobs).
                ->url(StaffAccess::isAdmin() ? url(config('horizon.path', 'horizon').'/failed') : null),
            // Paddle signature rejections are never stored as events (no payload kept), so they are
            // only counted here; a burst means a wrong webhook secret or someone probing the endpoint.
            Stat::make('Webhook errors', number_format($health['webhook_errors_week']))
                ->icon(Heroicon::OutlinedBolt)
                ->description('Failed RevenueCat/Paddle events · '.number_format($health['paddle_signature_rejections_week']).' Paddle bad signatures · 7 days')
                ->color($health['webhook_errors_week'] > 0 || $health['paddle_signature_rejections_week'] > 0 ? 'danger' : 'success')
                ->url(WebhookEventResource::getUrl('index', ['filters' => ['outcome' => ['value' => WebhookEvent::OUTCOME_FAILED]]])),
            Stat::make('AdMob SSV rejections', number_format($health['admob_ssv_rejections_week']))
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->description(number_format($health['admob_ssv_signature_invalid_week']).' bad signatures · 7 days (rewards not granted)')
                ->color($health['admob_ssv_signature_invalid_week'] > 0 ? 'danger' : ($health['admob_ssv_rejections_week'] > 0 ? 'warning' : 'success')),
            Stat::make('Push-ready devices', number_format($health['devices_with_push']))
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->description($pushShare.'% of '.number_format($health['devices']).' devices · '.number_format($health['failed_push_jobs']).' failed push jobs')
                ->descriptionColor($health['failed_push_jobs'] > 0 ? 'danger' : 'gray'),
            Stat::make('Pending deletions', number_format($health['deletion_requests_pending']))
                ->icon(Heroicon::OutlinedTrash)
                ->description('Awaiting email confirmation · '.number_format($health['deletion_requests_blocked_week']).' blocked in 7 days')
                ->color($health['deletion_requests_pending'] > 0 ? 'warning' : 'gray')
                ->url(AccountDeletionRequestResource::getUrl('index', ['filters' => ['status' => ['value' => AccountDeletionRequest::STATUS_PENDING]]])),
        ];
    }
}
