<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\OnboardingProgress;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OnboardingSteps;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * `GET /me/onboarding` (docs/API.md "Onboarding (FTUE)"): the user's stored steps plus the derived
 * ones, checked against real data. A derived step is stored the first time it is true and stays
 * done. Only derived steps that are not stored yet are checked, all in **one** query; a newly done
 * derived step costs one short write. The flow (`owner` / `member` / `teacher` / `student`) and its
 * required and optional steps come from the tier and the member's role.
 */
class GetOnboarding
{
    public function __construct(
        private readonly RecordOnboardingSteps $record,
        private readonly PolicyResolver $policy,
    ) {}

    /**
     * @return array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>}
     */
    public function handle(User $user): array
    {
        $progress = OnboardingProgress::query()->where('user_id', $user->id)->first();
        $member = $user->activeMembership();
        $member?->loadMissing('family');

        /** @var array<string, string> $done */
        $done = $progress->steps ?? [];
        $newlyDone = array_values(array_diff($this->derivedNow($member, array_keys($done)), array_keys($done)));

        if ($newlyDone !== []) {
            $progress = $this->record->handle($user, $newlyDone);
            $done = $progress->steps;
        }

        [$flow, $required, $optional] = $this->flow($user, $member);

        $steps = [];

        foreach (OnboardingSteps::ALL as $key) {
            $steps[$key] = $done[$key] ?? null;
        }

        return [
            'steps' => $steps,
            'dismissed_at' => Iso::format($progress?->dismissed_at),
            'flow' => $flow,
            'required_steps' => $required,
            'optional_steps' => $optional,
        ];
    }

    /**
     * Derived step keys that are true right now, skipping the ones already stored.
     *
     * @param  list<string>  $stored
     * @return list<string>
     */
    private function derivedNow(?FamilyMember $member, array $stored): array
    {
        if ($member === null) {
            return [];
        }

        $family = $member->family;
        $isClassroom = $family->tier === Family::TIER_CLASSROOM;
        $wanted = fn (string $key): bool => ! in_array($key, $stored, true);
        $true = ['create_family', 'code_sign_in'];

        if ($isClassroom && $wanted('code_cards') && $family->codes_issued_at !== null) {
            $true[] = 'code_cards';
        }

        $query = DB::query();
        $checks = 0;

        $add = function (string $alias, mixed $subquery) use ($query, &$checks): void {
            $query->selectSub($subquery, $alias);
            $checks++;
        };

        $activeMembers = fn () => FamilyMember::query()->where('family_id', $family->id)->where('status', FamilyMember::STATUS_ACTIVE);

        if ($wanted('add_member')) {
            $add('members_count', $activeMembers()->selectRaw('count(*)'));
            $add('pending_invite', Invite::query()->where('family_id', $family->id)->whereNull('used_at')->where('expires_at', '>', now())->selectRaw('1')->limit(1));
        }

        if ($wanted('co_admin')) {
            $add('admins_count', $activeMembers()->where('role', FamilyMember::ROLE_ADMIN)->selectRaw('count(*)'));
        }

        if ($wanted('first_game')) {
            $add('played', PlaySession::query()->where('member_id', $member->id)
                ->whereIn('status', [PlaySession::STATUS_COMPLETED, PlaySession::STATUS_FAILED])->selectRaw('1')->limit(1));
        }

        if ($wanted('first_goal')) {
            $add('has_goal', GoalPlan::query()->withTrashed()->where('family_id', $family->id)->selectRaw('1')->limit(1));
        }

        if ($wanted('say_hi')) {
            $add('said_hi', ChatMessage::query()->where('family_member_id', $member->id)->selectRaw('1')->limit(1));
        }

        if ($isClassroom && $wanted('class_details')) {
            $add('consent_current', Consent::query()
                ->where('type', Consent::CLASSROOM)
                ->where('version', LegalDocuments::classroomConsentVersion())
                ->whereIn('user_id', FamilyMember::query()->select('user_id')->where('family_id', $family->id)
                    ->where('status', FamilyMember::STATUS_ACTIVE)->where('role', FamilyMember::ROLE_ADMIN))
                ->selectRaw('1')->limit(1));
        }

        if ($isClassroom && $wanted('roster')) {
            $add('students_count', $activeMembers()->where('role', FamilyMember::ROLE_MEMBER)->selectRaw('count(*)'));
        }

        if ($checks === 0) {
            return $true;
        }

        /** @var object $row */
        $row = $query->first();
        $value = fn (string $alias): int => (int) ($row->{$alias} ?? 0);

        $rules = [
            'add_member' => fn () => $value('members_count') >= 2 || $value('pending_invite') === 1,
            'co_admin' => fn () => $value('admins_count') >= 2,
            'first_game' => fn () => $value('played') === 1,
            'first_goal' => fn () => $value('has_goal') === 1,
            'say_hi' => fn () => $value('said_hi') === 1,
            'class_details' => fn () => $isClassroom && $value('consent_current') === 1,
            'roster' => fn () => $isClassroom && $value('students_count') >= 1,
        ];

        foreach ($rules as $key => $rule) {
            if ($wanted($key) && $rule()) {
                $true[] = $key;
            }
        }

        return $true;
    }

    /**
     * The flow for this user (tier + role), with its ordered required steps and optional ones.
     *
     * @return array{0: string, 1: list<string>, 2: list<string>}
     */
    private function flow(User $user, ?FamilyMember $member): array
    {
        if ($member === null) {
            // No family yet (or no longer): the owner flow starts with "Create the family".
            return [OnboardingSteps::FLOW_OWNER, ['welcome', 'create_family', 'pick_world', 'add_member', 'first_game', 'first_goal', 'say_hi'], []];
        }

        $family = $member->family;
        $chatMode = $this->policy->effectiveChatMode($family);
        $chatOn = $member->chat_enabled;

        if ($family->tier === Family::TIER_CLASSROOM) {
            if ($member->role === FamilyMember::ROLE_ADMIN) {
                return [OnboardingSteps::FLOW_TEACHER, ['classroom_welcome', 'class_details', 'roster', 'code_cards', 'chat_mode', 'first_goal', 'end_of_year'], ['co_admin']];
            }

            $student = ['code_sign_in', 'avatar_world', 'first_game', 'family_goal'];

            if ($chatMode === Family::CHAT_MODE_NORMAL && $chatOn) {
                $student[] = 'say_hi';
            }

            return [OnboardingSteps::FLOW_STUDENT, $student, []];
        }

        if ((int) $family->owner_user_id !== (int) $user->id) {
            return [OnboardingSteps::FLOW_MEMBER, ['welcome_family', 'avatar_world', 'first_game', 'family_goal'], []];
        }

        $owner = ['welcome', 'create_family', 'pick_world', 'add_member'];

        if ($family->tier === Family::TIER_BIG_CLAN) {
            $owner[] = 'co_admin';
        }

        array_push($owner, 'first_game', 'first_goal');

        if ($chatMode !== Family::CHAT_MODE_OFF && $chatOn) {
            $owner[] = 'say_hi';
        }

        return [OnboardingSteps::FLOW_OWNER, $owner, []];
    }
}
