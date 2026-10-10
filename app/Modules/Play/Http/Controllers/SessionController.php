<?php

namespace App\Modules\Play\Http\Controllers;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController
{
    use CurrentMember;

    public function store(StartSessionRequest $request, StartSession $start): JsonResponse
    {
        $session = $start->handle($this->currentMember($request), $request->integer('category_id'));

        return (new SessionPayload($session))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $session): SessionPayload
    {
        return new SessionPayload($this->ownSession($request, $session));
    }

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
            'session' => (new SessionResource($result['session']))->resolve($request),
        ]]);
    }

    public function secondChance(SecondChanceRequest $request, int $session, GrantSecondChance $grant): SessionPayload
    {
        $rewardRef = $request->input('reward_ref');

        return new SessionPayload($grant->handle(
            $this->ownSession($request, $session),
            (string) $request->string('method'),
            is_string($rewardRef) ? $rewardRef : null,
        ));
    }

    public function redemption(Request $request, int $session, StartRedemption $start): SessionPayload
    {
        return new SessionPayload($start->handle($this->ownSession($request, $session)));
    }

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
