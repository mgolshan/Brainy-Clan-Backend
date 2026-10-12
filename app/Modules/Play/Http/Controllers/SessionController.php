<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Play\Actions\AbandonSession;
use App\Modules\Play\Actions\GrantSecondChance;
use App\Modules\Play\Actions\StartRedemption;
use App\Modules\Play\Actions\StartSession;
use App\Modules\Play\Actions\SubmitAnswer;
use App\Modules\Play\Http\Requests\SecondChanceRequest;
use App\Modules\Play\Http\Requests\StartSessionRequest;
use App\Modules\Play\Http\Requests\SubmitAnswerRequest;
use App\Modules\Play\Http\Resources\SessionPayload;
use App\Modules\Play\Http\Resources\SessionResource;
use App\Modules\Play\Models\PlaySession;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Play', 'Play sessions: items, answers (checked by the server), second chances and redemption rounds.', 4)]
class SessionController
{
    use CurrentMember;

    /**
     * Start a play session.
     *
     * Picks items from the category subtree in the member's difficulty range (least seen first) or
     * generates them (Sudoku, Math Sprint). Items never contain answers. 403
     * `category_not_allowed`.
     */
    #[ApiErrors('category_not_allowed')]
    public function store(StartSessionRequest $request, StartSession $start): JsonResponse
    {
        $session = $start->handle($this->currentMember($request), $request->integer('category_id'));

        return (new SessionPayload($session))->response()->setStatusCode(201);
    }

    /**
     * A play session with its items.
     *
     * Own sessions only.
     */
    public function show(Request $request, int $session): SessionPayload
    {
        return new SessionPayload($this->ownSession($request, $session));
    }

    /**
     * Submit an answer.
     *
     * The server checks the answer and updates the session. A correct answer earns the item's
     * `points`; reaching `target_points` completes the session, reaching `blunder_cap` mistakes
     * fails it. A malformed `response` is a 422 (not a mistake). `correct_response` has the same
     * shape as the item type's `response` (see docs/API.md "Challenge payloads").
     *
     * @response array{data: array{is_correct: bool, correct_response: array{choice_id?: string, grid?: list<list<int>>, value?: string, word?: string, found?: list<array{word: string, start: list<int>, end: list<int>}>}, explanation: string|null, points_awarded: int, session: SessionResource}}
     */
    #[ApiErrors('session_not_active', 'item_already_answered')]
    public function answer(SubmitAnswerRequest $request, int $session, SubmitAnswer $submit): JsonResponse
    {
        /** @var array<string, mixed> $response */
        $response = (array) $request->input('response', []);
        $timeMs = $request->input('time_ms');

        $result = $submit->handle(
            $this->ownSession($request, $session),
            $request->integer('session_item_id'),
            $response,
            is_numeric($timeMs) ? (int) $timeMs : null,
        );

        return new JsonResponse(['data' => [
            'is_correct' => $result['is_correct'],
            'correct_response' => $result['correct_response'],
            'explanation' => $result['explanation'],
            'points_awarded' => $result['points_awarded'],
            'session' => new SessionResource($result['session']),
        ]]);
    }

    /**
     * Use a second chance.
     *
     * Only when the session `failed`. `free` (Family tier, per day) or `ad` (rewarded ad). Resets
     * mistakes and returns all items plus fresh ones.
     */
    #[ApiErrors('second_chance_unavailable', 'session_not_active')]
    public function secondChance(SecondChanceRequest $request, int $session, GrantSecondChance $grant): SessionPayload
    {
        $rewardRef = $request->input('reward_ref');

        return new SessionPayload($grant->handle(
            $this->ownSession($request, $session),
            (string) $request->string('method'),
            is_string($rewardRef) ? $rewardRef : null,
        ));
    }

    /**
     * Start a redemption round.
     *
     * Only when the session `failed` and redemption is allowed: adds 3 easier items. All correct
     * → like a second chance; any wrong → `failed` for good.
     */
    #[ApiErrors('second_chance_unavailable', 'session_not_active')]
    public function redemption(Request $request, int $session, StartRedemption $start): SessionPayload
    {
        return new SessionPayload($start->handle($this->ownSession($request, $session)));
    }

    /**
     * Abandon a session.
     */
    #[ApiErrors('session_not_active')]
    public function abandon(Request $request, int $session, AbandonSession $abandon): SessionResource
    {
        return new SessionResource($abandon->handle($this->ownSession($request, $session)));
    }

    private function ownSession(Request $request, int $sessionId): PlaySession
    {
        $member = $this->currentMember($request);

        /** @var PlaySession $session */
        $session = PlaySession::query()->where('member_id', $member->id)->findOrFail($sessionId);
        $session->setRelation('member', $member);

        return $session;
    }
}
