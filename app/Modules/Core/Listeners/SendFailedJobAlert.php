<?php

namespace App\Modules\Core\Listeners;

use App\Modules\Core\Support\OpsAlert;
use Illuminate\Queue\Events\JobFailed;

/**
 * Mails (and posts to the optional webhook) when a queued job fails for good — Horizon has no
 * failure notification of its own. At most one alert per job class per
 * `ops.failed_job_alert_cooldown_minutes`. Only the job class, queue, connection and exception
 * class are sent: exception messages and payloads may hold personal data and stay in Horizon.
 */
class SendFailedJobAlert
{
    public function __construct(private readonly OpsAlert $alert) {}

    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        $queue = $event->job->getQueue();
        $cooldownMinutes = max(1, (int) config('ops.failed_job_alert_cooldown_minutes', 15));

        $this->alert->send(
            'failed-job|'.$jobName,
            'Queued job failed: '.class_basename($jobName),
            implode("\n", [
                'A queued job failed and was moved to the failed jobs (tries = 1).',
                '',
                'Job: '.$jobName,
                'Queue: '.$event->connectionName.' / '.$queue,
                'Exception: '.$event->exception::class,
                'Time (UTC): '.now()->utc()->toDateTimeString(),
                '',
                'Details (staff only): '.rtrim((string) config('app.url'), '/').'/'.trim((string) config('horizon.path', 'horizon'), '/').'/failed',
                "Further failures of this job within {$cooldownMinutes} minutes are not mailed again.",
                'Runbook: docs/OPERATIONS_HANDBOOK.md §5.5 "Queue backlog / Horizon down".',
            ]),
            $cooldownMinutes * 60,
        );
    }
}
