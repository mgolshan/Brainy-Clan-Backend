<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Support\GoalRecipients;
use App\Modules\Notifications\Support\PushLocale;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: tells the plan's members that the goal was reached (cooperation) or who reached it (competition).
 * Texts in each recipient's language ({@see PushLocale}). Push `data`: `{ "type": "goal_reached",
 * "goal_id": …, "period_id": … }`.
 */
class NotifyGoalReached implements ShouldQueue
{
    /** @var array<string, array{title: string, team: string, winner: string, winners: string, anyone: string, and: string}> */
    public const array TEXTS = [
        'en' => [
            'title' => 'Goal reached!',
            'team' => 'Team goal reached: :goal!',
            'winner' => ':names reached the goal in :goal!',
            'winners' => ':names reached the goal in :goal!',
            'anyone' => 'The goal in :goal was reached!',
            'and' => ' and ',
        ],
        'fr' => [
            'title' => 'Objectif atteint !',
            'team' => 'Objectif d’équipe atteint : :goal !',
            'winner' => ':names a atteint l’objectif : :goal !',
            'winners' => ':names ont atteint l’objectif : :goal !',
            'anyone' => 'Objectif atteint : :goal !',
            'and' => ' et ',
        ],
    ];

    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(GoalReached $event): void
    {
        $loaded = GoalRecipients::load($event->planId);

        if ($loaded === null) {
            return;
        }

        [$plan, $members] = $loaded;

        $winners = $members->whereIn('id', $event->winnerMemberIds)->map(fn (FamilyMember $member) => $member->nickname)->values();

        foreach (PushLocale::group($members) as $locale => $group) {
            $texts = self::TEXTS[$locale];

            $body = match (true) {
                $plan->mode === GoalPlan::MODE_COOPERATION => $texts['team'],
                $winners->isEmpty() => $texts['anyone'],
                default => $texts[$winners->count() === 1 ? 'winner' : 'winners'],
            };

            $this->send->handle($group, 'goal_reached', $texts['title'], strtr($body, [
                ':names' => $winners->join(', ', $texts['and']),
                ':goal' => $plan->name,
            ]), [
                'goal_id' => $plan->id,
                'period_id' => $event->periodId,
            ], CarbonImmutable::now());
        }
    }
}
