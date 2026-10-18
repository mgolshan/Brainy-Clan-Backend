<?php

namespace App\Filament\Support;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Family\Models\AccountDeletionRequest;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates for the admin dashboard widgets (docs/PLAN.md "Admin dashboard").
 *
 * Every section is a fixed, small number of GROUP BY / COUNT queries, whatever the number of
 * families or sessions, cached for at most {@see self::TTL_SECONDS} seconds (dashboard figures may
 * be a minute old; resource pages stay live). Days are UTC calendar days (`app.timezone` is UTC),
 * not the families' own time zones. Results are plain arrays so they serialize into Redis.
 */
class DashboardMetrics
{
    public const int TTL_SECONDS = 60;

    /** Days shown in the long trend chart. */
    public const int TREND_DAYS = 30;

    /** Days in the short window (sparklines, "7 d" figures). */
    public const int WEEK_DAYS = 7;

    private const string CACHE_PREFIX = 'admin:dashboard:v1:';

    /**
     * Families, members, active players, plays and points.
     *
     * @return array{
     *     families: int, families_new_week: int, families_on_family_plan: int,
     *     members: int, players_today: int, players_week: int,
     *     plays_today: int, plays_week: int, plays_daily: array<string, int>,
     *     points_today: int, points_week: int, points_daily: array<string, int>
     * }
     */
    public function activity(): array
    {
        return $this->remember('activity', function (): array {
            $today = $this->today();
            $weekStart = $this->weekStart();

            $families = DB::table('families')
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) AS new_week', [$weekStart])
                ->selectRaw("COALESCE(SUM(CASE WHEN tier = 'family' THEN 1 ELSE 0 END), 0) AS family_plan")
                ->first();

            $players = DB::table('play_sessions')
                ->where('started_at', '>=', $weekStart)
                ->selectRaw('COUNT(DISTINCT member_id) AS week')
                ->selectRaw('COUNT(DISTINCT CASE WHEN started_at >= ? THEN member_id END) AS today', [$today])
                ->first();

            $plays = $this->dailyPlays();
            $playsWeek = array_slice($plays, -self::WEEK_DAYS, preserve_keys: true);

            $points = $this->daily(
                DB::table('point_entries')->where('amount', '>', 0)->where('created_at', '>=', $weekStart),
                'created_at',
                'SUM(amount)',
                $weekStart,
                self::WEEK_DAYS,
            );

            return [
                'families' => (int) $families?->total,
                'families_new_week' => (int) $families?->new_week,
                'families_on_family_plan' => (int) $families?->family_plan,
                'members' => FamilyMember::query()->where('status', FamilyMember::STATUS_ACTIVE)->count(),
                'players_today' => (int) $players?->today,
                'players_week' => (int) $players?->week,
                'plays_today' => (int) end($playsWeek),
                'plays_week' => array_sum($playsWeek),
                'plays_daily' => $playsWeek,
                'points_today' => (int) end($points),
                'points_week' => array_sum($points),
                'points_daily' => $points,
            ];
        });
    }

    /**
     * Plays started and families created per UTC day over the last {@see self::TREND_DAYS} days.
     *
     * @return array{plays: array<string, int>, families: array<string, int>}
     */
    public function trend(): array
    {
        return $this->remember('trend', function (): array {
            $start = $this->today()->subDays(self::TREND_DAYS - 1);

            return [
                'plays' => $this->dailyPlays(),
                'families' => $this->daily(
                    DB::table('families')->where('created_at', '>=', $start),
                    'created_at',
                    'COUNT(*)',
                    $start,
                    self::TREND_DAYS,
                ),
            ];
        });
    }

    /**
     * Plays started in the last 7 days per top-level category, busiest first.
     *
     * @return array<string, int> category name => plays
     */
    public function playsPerCategory(): array
    {
        return $this->remember('plays-per-category', function (): array {
            $rows = DB::table('play_sessions')
                ->where('started_at', '>=', $this->weekStart())
                ->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) AS plays')
                ->get();

            $tree = app(CategoryTree::class);
            $perRoot = [];

            foreach ($rows as $row) {
                $name = $tree->rootOf((int) $row->category_id)?->localizedName() ?? 'Category #'.$row->category_id;
                $perRoot[$name] = ($perRoot[$name] ?? 0) + (int) $row->plays;
            }

            arsort($perRoot);

            return $perRoot;
        });
    }

    /**
     * Subscriptions by state, and the content bank.
     *
     * @return array{
     *     subscriptions_active: int, subscriptions_trial: int, subscriptions_grace: int,
     *     content_published: int, content_published_by_locale: array<string, int>,
     *     content_awaiting_review: int, batches_awaiting_review: int
     * }
     */
    public function business(): array
    {
        return $this->remember('business', function (): array {
            $subscriptions = DB::table('subscriptions')
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE])
                ->groupBy('status', 'period_type')
                ->selectRaw('status, period_type, COUNT(*) AS total')
                ->get();

            $active = $trial = $grace = 0;

            foreach ($subscriptions as $row) {
                match (true) {
                    $row->status === Subscription::STATUS_GRACE => $grace += (int) $row->total,
                    $row->period_type === 'trial' => $trial += (int) $row->total,
                    default => $active += (int) $row->total,
                };
            }

            $content = DB::table('content_items')
                ->whereIn('status', [ContentItem::STATUS_PUBLISHED, ContentItem::STATUS_DRAFT, ContentItem::STATUS_REVIEW])
                ->groupBy('status', 'locale')
                ->selectRaw('status, locale, COUNT(*) AS total')
                ->get();

            $published = [];
            $awaitingReview = 0;

            foreach ($content as $row) {
                if ($row->status === ContentItem::STATUS_PUBLISHED) {
                    $published[$row->locale] = ($published[$row->locale] ?? 0) + (int) $row->total;
                } else {
                    $awaitingReview += (int) $row->total;
                }
            }

            ksort($published);

            return [
                'subscriptions_active' => $active,
                'subscriptions_trial' => $trial,
                'subscriptions_grace' => $grace,
                'content_published' => array_sum($published),
                'content_published_by_locale' => $published,
                'content_awaiting_review' => $awaitingReview,
                'batches_awaiting_review' => ContentBatch::query()->where('status', ContentBatch::STATUS_REVIEW)->count(),
            ];
        });
    }

    /**
     * Failed jobs, webhook errors, push readiness and account-deletion requests.
     *
     * @return array{
     *     failed_jobs: int, failed_jobs_day: int, failed_push_jobs: int,
     *     webhook_errors_week: int, devices: int, devices_with_push: int,
     *     deletion_requests_pending: int, deletion_requests_blocked_week: int
     * }
     */
    public function health(): array
    {
        return $this->remember('health', function (): array {
            $now = CarbonImmutable::now();
            $weekStart = $this->weekStart();

            $failedJobs = DB::table('failed_jobs')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COALESCE(SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END), 0) AS day', [$now->subDay()])
                // The queued job's class name is in the serialized payload.
                ->selectRaw('COALESCE(SUM(CASE WHEN payload LIKE ? THEN 1 ELSE 0 END), 0) AS push', ['%'.class_basename(DeliverPushNotification::class).'%'])
                ->first();

            $devices = DB::table('devices')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COUNT(push_token) AS with_push')
                ->first();

            $deletions = DB::table('account_deletion_requests')
                ->where('created_at', '>=', $weekStart)
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND expires_at > ? THEN 1 ELSE 0 END), 0) AS pending', [AccountDeletionRequest::STATUS_PENDING, $now])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS blocked', [AccountDeletionRequest::STATUS_BLOCKED])
                ->first();

            return [
                'failed_jobs' => (int) $failedJobs?->total,
                'failed_jobs_day' => (int) $failedJobs?->day,
                'failed_push_jobs' => (int) $failedJobs?->push,
                'webhook_errors_week' => WebhookEvent::query()
                    ->where('outcome', WebhookEvent::OUTCOME_FAILED)
                    ->where('created_at', '>=', $weekStart)
                    ->count(),
                'devices' => (int) $devices?->total,
                'devices_with_push' => (int) $devices?->with_push,
                'deletion_requests_pending' => (int) $deletions?->pending,
                'deletion_requests_blocked_week' => (int) $deletions?->blocked,
            ];
        });
    }

    /** Start of the current UTC day. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfDay();
    }

    /** Start of the 7-day window that ends today (today included). */
    public function weekStart(): CarbonImmutable
    {
        return $this->today()->subDays(self::WEEK_DAYS - 1);
    }

    /**
     * Plays started per UTC day over the trend window (shared by the stats and the chart).
     *
     * @return array<string, int>
     */
    private function dailyPlays(): array
    {
        $start = $this->today()->subDays(self::TREND_DAYS - 1);

        return $this->daily(
            DB::table('play_sessions')->where('started_at', '>=', $start),
            'started_at',
            'COUNT(*)',
            $start,
            self::TREND_DAYS,
        );
    }

    /**
     * One row per day with data (`DATE()` works in MySQL and SQLite), filled to a full series.
     *
     * @return array<string, int> Y-m-d => value, oldest first, every day present
     */
    private function daily(Builder $query, string $column, string $aggregate, CarbonImmutable $start, int $days): array
    {
        $rows = $query
            ->groupByRaw("DATE({$column})")
            ->selectRaw("DATE({$column}) AS day, {$aggregate} AS value")
            ->pluck('value', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i)->toDateString();
            $series[$day] = (int) ($rows[$day] ?? 0);
        }

        return $series;
    }

    /**
     * @template T of array
     *
     * @param  callable(): T  $compute
     * @return T
     */
    private function remember(string $section, callable $compute): array
    {
        return Cache::remember(self::CACHE_PREFIX.$section, self::TTL_SECONDS, $compute);
    }
}
