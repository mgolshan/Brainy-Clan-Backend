<?php

namespace App\Modules\Core\Listeners;

use App\Modules\Core\Support\OpsAlert;
use Laravel\Horizon\Events\LongWaitDetected;

/**
 * Horizon mails its own "long wait" notification (`Horizon::routeMailNotificationsTo`); this
 * forwards the same event to the optional ops webhook, at most once per queue every 5 minutes
 * (Horizon's own interval).
 */
class ForwardLongWaitToWebhook
{
    public function __construct(private readonly OpsAlert $alert) {}

    public function handle(LongWaitDetected $event): void
    {
        $this->alert->webhookOnly(
            'long-wait|'.$event->connection.'|'.$event->queue,
            "Long queue wait: {$event->connection} / {$event->queue} has waited {$event->seconds} s (thresholds in config/horizon.php `waits`).",
            300,
        );
    }
}
