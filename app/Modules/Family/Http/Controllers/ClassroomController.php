<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\AcceptClassroomConsent;
use App\Modules\Family\Actions\AddClassroomRoster;
use App\Modules\Family\Actions\BuildClassroomSummary;
use App\Modules\Family\Actions\IssueClassroomCodeCards;
use App\Modules\Family\Actions\StartClassroomNewYear;
use App\Modules\Family\Http\Requests\ClassroomCodeCardsRequest;
use App\Modules\Family\Http\Requests\ClassroomConsentRequest;
use App\Modules\Family\Http\Requests\ClassroomNewYearRequest;
use App\Modules\Family\Http\Requests\ClassroomRosterRequest;
use App\Modules\Family\Http\Requests\ClassroomSummaryRequest;
use App\Modules\Family\Http\Resources\ClassroomCodeCardResource;
use App\Modules\Family\Http\Resources\FamilyResource;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\ClassroomSummaryCsv;
use App\Modules\Policy\Actions\EvaluateWorldUnlocks;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Classroom tools (docs/API.md "Classroom tools", B12 step 3): family admins of a Classroom-tier
 * family only (403 `plan_required` on other tiers). Everything except the consent itself needs the
 * current consent statement (409 `classroom_consent_required`). Both checks run in the `classroom`
 * middleware, before validation.
 */
#[Group('Family')]
class ClassroomController
{
    use CurrentMember;

    /**
     * Accept the Classroom consent statement.
     *
     * The teacher confirms they are authorised by their school or organisation, or have the
     * parents' or guardians' consent, to add the students. `version` must be the current one from
     * `GET /legal` (`classroom_consent.version`), else 422. Stored per user (version, time, hashed IP).
     */
    #[ApiErrors('plan_required')]
    public function consent(ClassroomConsentRequest $request, AcceptClassroomConsent $accept): FamilyResource
    {
        $family = $accept->handle($this->currentMember($request), (string) $request->string('version'), $request->ip());

        return new FamilyResource($family);
    }

    /**
     * Add students (roster).
     *
     * 1–35 students in one go, without invites: active members with role `member` and no email or
     * password (they sign in with code cards). `birth_year` is the class's year; a student may
     * override it; the birth month is taken as June. Nicknames 1–40 characters, unique in the family.
     * 403 `member_limit_reached` when the roster would pass `max_members` (pending invites count),
     * 403 `adult_limit_reached` when adult-aged students would pass `max_adults`.
     */
    #[ApiErrors('plan_required', 'classroom_consent_required', 'member_limit_reached', 'adult_limit_reached')]
    public function roster(ClassroomRosterRequest $request, AddClassroomRoster $roster, EvaluateWorldUnlocks $worlds): JsonResponse
    {
        /** @var array{birth_year: int, students: list<array{nickname: string, birth_year?: int|null}>} $data */
        $data = $request->validated();
        $viewer = $this->currentUser($request);

        $members = $roster->handle($this->currentMember($request), $data);
        $progress = $worlds->progressForMembers($members);

        return new JsonResponse([
            'data' => $members->map(fn (FamilyMember $member) => MemberResource::forViewer($member, $viewer, $progress[$member->id] ?? null)->resolve($request))->values()->all(),
        ], 201);
    }

    /**
     * Issue sign-in code cards.
     *
     * One-time sign-in codes (`POST /auth/code`) valid 14 days (`sign_in_codes.card_ttl_days`), one
     * per member: every non-admin member by default, or `member_ids` (active members of the family,
     * else 422). Issuing voids each member's older unused codes and sets `classroom.codes_issued_at`.
     * Sorted by nickname. The app prints the cards; codes never go into URLs. 10 calls per hour per
     * family (429 `rate_limited`).
     *
     * @response 201 array{data: list<array{member_id: int, nickname: string, avatar: string|null, code: string, expires_at: string}>}
     */
    #[ApiErrors('plan_required', 'classroom_consent_required')]
    public function codeCards(ClassroomCodeCardsRequest $request, IssueClassroomCodeCards $issue): JsonResponse
    {
        $cards = $issue->handle($this->currentMember($request), $request->memberIds(), $this->currentUser($request));

        return ClassroomCodeCardResource::collection($cards)->response()->setStatusCode(201);
    }

    /**
     * Class summary.
     *
     * One row per active member, sorted by nickname: points, games, accuracy, streaks and the last
     * answer, for `period=season` (default: from `season_started_at`, or everything without a
     * season) or `all`.
     *
     * @response array{data: array{generated_at: string, season_started_at: string|null, rows: list<array{member_id: int, nickname: string, role: string, points: int, games: int, accuracy_percent: int|null, current_streak: int, longest_streak: int, last_played_at: string|null}>}}
     */
    #[ApiErrors('plan_required', 'classroom_consent_required')]
    public function summary(ClassroomSummaryRequest $request, BuildClassroomSummary $summary): JsonResponse
    {
        return new JsonResponse(['data' => $summary->handle($this->currentMember($request)->family, $request->period())]);
    }

    /**
     * Class summary (CSV).
     *
     * The same rows as `GET /family/classroom/summary` as a CSV download (UTF-8 with BOM), with the
     * column headers in the requester's locale. Cells a spreadsheet would read as a formula start
     * with `'`.
     */
    #[ApiErrors('plan_required', 'classroom_consent_required')]
    public function summaryCsv(ClassroomSummaryRequest $request, BuildClassroomSummary $summary): StreamedResponse
    {
        $data = $summary->handle($this->currentMember($request)->family, $request->period());
        $csv = ClassroomSummaryCsv::render($data['rows'], $this->currentUser($request)->locale);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'class-summary-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Archive & start a new year.
     *
     * Cannot be undone (offer the summary export first). `confirm_name` must equal the class name
     * (case and surrounding spaces ignored), else 422; at least one `clear` flag must be true.
     * `students` removes every non-admin member, `points` starts a new season (nothing is deleted;
     * family totals count from it), `chat` deletes every message, `goals` deletes every goal plan.
     */
    #[ApiErrors('plan_required', 'classroom_consent_required')]
    public function newYear(ClassroomNewYearRequest $request, StartClassroomNewYear $newYear): FamilyResource
    {
        $family = $newYear->handle($this->currentMember($request), (string) $request->string('confirm_name'), $request->flags());

        return new FamilyResource($family);
    }
}
