<?php

namespace App\Modules\Family\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Support\Facades\DB;

/**
 * `POST /family/classroom/new-year` (admin, Classroom, consent given; docs/API.md "Classroom tools" →
 * "Archive & start a new year"). Cannot be undone; the app offers the summary export first.
 *
 * - `confirm_name` must equal the class name, ignoring case and surrounding spaces (422 otherwise);
 *   at least one `clear` flag must be true (the Form Request checks that).
 * - `students`: every active non-admin member goes through the normal removal ({@see RemoveMember}:
 *   sign-in codes voided, `MemberRemoved` listeners, accounts without their own login erased).
 * - `points`: a new season, `families.season_started_at = now`. The append-only ledger is never
 *   touched; family-scoped totals (leaderboard, class summary, goal progress) count from the season.
 * - `chat`: every message of the family is deleted for good (reactions and reports cascade).
 * - `goals`: every goal plan is soft-deleted (as `DELETE /goals/{plan}`).
 * - One activity log entry with the flags, counts and the admin's user id: no names.
 * All in one transaction.
 */
class StartClassroomNewYear
{
    public const array CLEAR_FLAGS = ['students', 'points', 'chat', 'goals'];

    public function __construct(private readonly RemoveMember $removeMember) {}

    /**
     * @param  array{students?: bool, points?: bool, chat?: bool, goals?: bool}  $clear
     */
    public function handle(FamilyMember $admin, string $confirmName, array $clear): Family
    {
        $family = $admin->family;

        if (mb_strtolower(trim($confirmName)) !== mb_strtolower(trim($family->name))) {
            throw ApiException::validation('confirm_name', 'Type the class name exactly as it is shown to confirm.');
        }

        $flags = array_map(fn (string $flag): bool => (bool) ($clear[$flag] ?? false), array_combine(self::CLEAR_FLAGS, self::CLEAR_FLAGS));

        return DB::transaction(function () use ($admin, $family, $flags): Family {
            $counts = ['students_removed' => 0, 'chat_messages_deleted' => 0, 'goal_plans_deleted' => 0];

            if ($flags['students']) {
                $students = $family->members()
                    ->where('role', FamilyMember::ROLE_MEMBER)
                    ->with(['user' => fn ($query) => $query->withExists('socialAccounts')])
                    ->get();

                foreach ($students as $student) {
                    $student->setRelation('family', $family);
                    $this->removeMember->handle($student);
                }

                $counts['students_removed'] = $students->count();
            }

            if ($flags['points']) {
                $family->forceFill(['season_started_at' => now()])->save();
            }

            if ($flags['chat']) {
                $counts['chat_messages_deleted'] = ChatMessage::query()->where('family_id', $family->id)->delete();
            }

            if ($flags['goals']) {
                $counts['goal_plans_deleted'] = GoalPlan::query()->where('family_id', $family->id)->update(['deleted_at' => now()]);
            }

            activity('family')
                ->performedOn($family)
                ->causedBy($admin->user)
                ->event('classroom_new_year')
                ->withProperties(['clear' => $flags, ...$counts])
                ->log('Classroom new year started');

            return $family;
        });
    }
}
