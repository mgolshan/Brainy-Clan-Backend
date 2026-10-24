<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Support\Iso;
use App\Modules\Core\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * The deep health check (`GET /api/v1/health/deep`, docs/API.md "Health"): MySQL, Redis, the
 * scheduler heartbeat and Horizon are critical (any failure → `status: fail`, HTTP 503); failed
 * jobs in the last hour only warn (`status: degraded`, HTTP 200). Cheap: one `SELECT 1`, one
 * `PING`, one cache read, one Redis read for Horizon and one indexed COUNT. Never returns error
 * messages, hosts or versions — a failing check only says `fail`.
 */
class RunHealthChecks
{
    public const string OK = 'ok';

    public const string WARN = 'warn';

    public const string FAIL = 'fail';

    public function __construct(
        private readonly SchedulerHeartbeat $heartbeat,
        private readonly MasterSupervisorRepository $masters,
    ) {}

    /**
     * @return array{
     *     status: 'ok'|'degraded'|'fail',
     *     checks: array{
     *         database: array{status: string},
     *         redis: array{status: string},
     *         scheduler: array{status: string, age_seconds: int|null},
     *         horizon: array{status: string, state: string},
     *         failed_jobs: array{status: string, last_hour: int|null}
     *     },
     *     time: string
     * }
     */
    public function handle(): array
    {
        $checks = [
            'database' => ['status' => $this->attempt(fn () => DB::select('select 1'))],
            'redis' => ['status' => $this->attempt(fn () => Redis::connection()->ping())],
            'scheduler' => $this->scheduler(),
            'horizon' => $this->horizon(),
            'failed_jobs' => $this->failedJobs(),
        ];

        $critical = ['database', 'redis', 'scheduler', 'horizon'];
        $status = match (true) {
            collect($critical)->contains(fn (string $name): bool => $checks[$name]['status'] !== self::OK) => 'fail',
            $checks['failed_jobs']['status'] !== self::OK => 'degraded',
            default => 'ok',
        };

        return ['status' => $status, 'checks' => $checks, 'time' => (string) Iso::format(now())];
    }

    /**
     * @return array{status: string, age_seconds: int|null}
     */
    private function scheduler(): array
    {
        try {
            $age = $this->heartbeat->ageSeconds();
        } catch (Throwable) {
            return ['status' => self::FAIL, 'age_seconds' => null];
        }

        $maxAge = (int) config('ops.health.scheduler_max_age_seconds', 180);

        return ['status' => $age !== null && $age < $maxAge ? self::OK : self::FAIL, 'age_seconds' => $age];
    }

    /**
     * Same rule as `horizon:status`: no master supervisor → inactive; all paused → paused.
     *
     * @return array{status: string, state: string}
     */
    private function horizon(): array
    {
        try {
            $masters = $this->masters->all();
        } catch (Throwable) {
            return ['status' => self::FAIL, 'state' => 'unknown'];
        }

        $state = match (true) {
            $masters === [] => 'inactive',
            collect($masters)->every(fn (object $master): bool => ($master->status ?? null) === 'paused') => 'paused',
            default => 'running',
        };

        return ['status' => $state === 'running' ? self::OK : self::FAIL, 'state' => $state];
    }

    /**
     * @return array{status: string, last_hour: int|null}
     */
    private function failedJobs(): array
    {
        try {
            $count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
        } catch (Throwable) {
            return ['status' => self::FAIL, 'last_hour' => null];
        }

        return ['status' => $count > 0 ? self::WARN : self::OK, 'last_hour' => $count];
    }

    /**
     * @param  callable(): mixed  $probe
     */
    private function attempt(callable $probe): string
    {
        try {
            $probe();

            return self::OK;
        } catch (Throwable) {
            return self::FAIL;
        }
    }
}
