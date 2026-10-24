<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Support\GoalRecipients;
use App\Modules\Notifications\Support\PushLocale;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: tells the plan's members that a goal period ended and the results are in.
 * Texts in each recipient's language ({@see PushLocale}). Push `data`: `{ "type": "period_closed",
 * "goal_id": …, "period_id": … }`.
 */
class NotifyPeriodClosed implements ShouldQueue
{
    /** @var array<string, array{title: string, body: string}> */
    public const array TEXTS = [
        'en' => ['title' => 'Results are in', 'body' => ':goal: see how everyone did.'],
        'fr' => ['title' => 'Les résultats sont là', 'body' => ':goal : voici les résultats de chacun.'],
    ];

    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(PeriodClosed $event): void
    {
        $loaded = GoalRecipients::load($event->planId);

        if ($loaded === null) {
            return;
        }

        [$plan, $members] = $loaded;

        foreach (PushLocale::group($members) as $locale => $group) {
            $texts = self::TEXTS[$locale];

            $this->send->handle($group, 'period_closed', $texts['title'], strtr($texts['body'], [':goal' => $plan->name]), [
                'goal_id' => $plan->id,
                'period_id' => $event->periodId,
            ], CarbonImmutable::now());
        }
    }
}
