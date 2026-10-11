<?php

namespace App\Modules\Play\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Play\Events\SessionCompleted;
use App\Modules\Play\Models\MemberItemHistory;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SecondChance;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Support\Facades\DB;

/**
 * The server is the referee: checks a response with the item's challenge type plugin, awards
 * points (1 per correct regular answer), counts mistakes and moves the session through
 * active → completed / failed. Redemption items decide the redemption round instead.
 */
class SubmitAnswer
{
    public function __construct(
        private readonly ChallengeTypeRegistry $registry,
        private readonly AddSessionItems $addItems,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @return array{is_correct: bool, correct_response: array<string, mixed>, explanation: string|null, points_awarded: int, session: PlaySession}
     */
    public function handle(PlaySession $session, int $sessionItemId, array $response, ?int $timeMs): array
    {
        return DB::transaction(function () use ($session, $sessionItemId, $response, $timeMs): array {
            /** @var PlaySession $session */
            $session = PlaySession::query()->with('member.family', 'member.user')->lockForUpdate()->findOrFail($session->id);

            /** @var SessionItem|null $item */
            $item = SessionItem::query()->with('contentItem')->where('play_session_id', $session->id)->find($sessionItemId);

            if ($item === null) {
                throw ApiException::validation('session_item_id', 'This item does not belong to the session.');
            }

            if ($item->isAnswered()) {
                throw ApiException::itemAlreadyAnswered();
            }

            $expectedStatus = $item->is_redemption ? PlaySession::STATUS_REDEMPTION : PlaySession::STATUS_ACTIVE;

            if ($session->status !== $expectedStatus) {
                throw ApiException::sessionNotActive();
            }

            $content = $item->contentItem;

            if ($content === null) {
                throw ApiException::sessionNotActive('This item is no longer available.');
            }

            $handler = $this->registry->get($content->type_key);
            $responseErrors = $handler->validateResponse($content, $response);

            if ($responseErrors !== []) {
                throw ApiException::validation((string) array_key_first($responseErrors), $responseErrors[array_key_first($responseErrors)][0]);
            }

            $isCorrect = $handler->check($content, $response);
            $points = $item->is_redemption ? 0 : $handler->score($content, $isCorrect);

            $item->forceFill([
                'response' => $response,
                'is_correct' => $isCorrect,
                'points_awarded' => $points,
                'time_ms' => $timeMs,
                'answered_at' => now(),
            ])->save();

            if ($isCorrect) {
                MemberItemHistory::query()
                    ->where('member_id', $session->member_id)
                    ->where('content_item_id', $content->id)
                    ->increment('times_correct');
            }

            $completed = $item->is_redemption
                ? $this->applyRedemptionAnswer($session, $isCorrect)
                : $this->applyRegularAnswer($session, $isCorrect, $points);

            AnswerSubmitted::dispatch($item->id, $session->id, $session->family_id, $session->member_id, $content->category_id, $isCorrect, $points, $item->is_redemption);

            if ($completed) {
                SessionCompleted::dispatch($session->id, $session->family_id, $session->member_id, $session->points);
            }

            return [
                'is_correct' => $isCorrect,
                'correct_response' => $handler->correctResponse($content),
                'explanation' => $handler->explanation($content),
                'points_awarded' => $points,
                'session' => $session,
            ];
        });
    }

    /**
     * @return bool whether the session just completed
     */
    private function applyRegularAnswer(PlaySession $session, bool $isCorrect, int $points): bool
    {
        if ($isCorrect) {
            $session->points += $points;
        } else {
            $session->mistakes++;
        }

        if ($session->points >= $session->target_points) {
            $session->status = PlaySession::STATUS_COMPLETED;
            $session->completed_at = now();
        } elseif ($session->mistakes >= $session->blunder_cap) {
            $session->status = PlaySession::STATUS_FAILED;
        }

        $session->save();

        return $session->status === PlaySession::STATUS_COMPLETED;
    }

    /**
     * Any wrong answer fails the round (no more redemption for this session); all items right
     * counts as a second chance.
     */
    private function applyRedemptionAnswer(PlaySession $session, bool $isCorrect): bool
    {
        if (! $isCorrect) {
            $session->status = PlaySession::STATUS_FAILED;
            $session->redemption_failed = true;
            $session->save();

            return false;
        }

        $remaining = SessionItem::query()
            ->where('play_session_id', $session->id)
            ->where('is_redemption', true)
            ->whereNull('answered_at')
            ->exists();

        if (! $remaining) {
            SecondChance::query()->create([
                'play_session_id' => $session->id,
                'member_id' => $session->member_id,
                'method' => SecondChance::METHOD_REDEMPTION,
                'granted_at' => now(),
            ]);

            $session->status = PlaySession::STATUS_ACTIVE;
            $session->mistakes = 0;
            $session->second_chances_used++;
            $session->save();

            $this->addItems->topUp($session);
        }

        return false;
    }
}
