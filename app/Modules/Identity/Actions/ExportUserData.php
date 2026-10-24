<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Scoring\Models\PointEntry;

/**
 * `GET /me/export` (right of access: PIPEDA, Quebec Law 25, GDPR-style): everything we hold about
 * the user, as plain data. Never includes secrets: no password hash, no API/push tokens, no Apple
 * refresh tokens, no IP hashes. A fixed number of queries however much history there is.
 */
class ExportUserData
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * Children get 403 `forbidden`: their parent asks support for an export.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        if ($this->policy->isChildUser($user)) {
            throw ApiException::forbidden('Ask your parent to request a copy of your data.');
        }

        $memberships = FamilyMember::query()
            ->where('user_id', $user->id)
            ->with('family:id,name,timezone,country_code,tier')
            ->orderBy('id')
            ->get();
        $memberIds = $memberships->pluck('id')->all();

        $preferences = NotificationPreference::query()->whereIn('family_member_id', $memberIds)->get()->keyBy('family_member_id');

        $pointEntries = PointEntry::query()
            ->whereIn('member_id', $memberIds)
            ->orderBy('id')
            ->get(['id', 'member_id', 'category_id', 'play_session_id', 'amount', 'reason', 'created_at']);

        $sessions = PlaySession::query()
            ->whereIn('member_id', $memberIds)
            ->with('category:id,slug')
            ->orderBy('id')
            ->get();

        $goalPlans = GoalPlan::query()
            ->withTrashed()
            ->whereHas('planMembers', fn ($query) => $query->whereIn('member_id', $memberIds))
            ->with(['planMembers' => fn ($query) => $query->whereIn('member_id', $memberIds)])
            ->orderBy('id')
            ->get();

        // The user's own family chat messages that still exist (not deleted, not past retention yet).
        $chatMessages = ChatMessage::query()
            ->whereIn('family_member_id', $memberIds)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'family_id', 'family_member_id', 'body', 'created_at']);

        $consents = Consent::query()->where('user_id', $user->id)->orderBy('id')->get();
        $devices = Device::query()->where('user_id', $user->id)->orderBy('id')->get();
        $socialAccounts = SocialAccount::query()->where('user_id', $user->id)->orderBy('id')->get();

        return [
            'exported_at' => Iso::format(now()),
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar,
                'email' => $user->email,
                'has_password' => $user->password !== null,
                'birth_year' => $user->birth_year,
                'birth_month' => $user->birth_month,
                'locale' => $user->locale,
                'created_at' => Iso::format($user->created_at),
            ],
            'memberships' => $memberships->map(function (FamilyMember $member) use ($preferences): array {
                $preference = $preferences->get($member->id);

                return [
                    'id' => $member->id,
                    'family' => [
                        'id' => $member->family->id,
                        'name' => $member->family->name,
                        'timezone' => $member->family->timezone,
                        'country_code' => $member->family->country_code,
                        'tier' => $member->family->tier,
                    ],
                    'role' => $member->role,
                    'nickname' => $member->nickname,
                    'status' => $member->status,
                    'difficulty_override' => $member->difficulty_override,
                    'accessibility' => $member->accessibility,
                    'notification_preferences' => $preference instanceof NotificationPreference ? [
                        'types' => $preference->types,
                        'quiet_from' => $preference->quiet_from,
                        'quiet_to' => $preference->quiet_to,
                        'reminder_time' => $preference->reminder_time,
                    ] : null,
                    'joined_at' => Iso::format($member->joined_at),
                ];
            })->values()->all(),
            'point_entries' => $pointEntries->map(fn (PointEntry $entry): array => [
                'id' => $entry->id,
                'member_id' => $entry->member_id,
                'category_id' => $entry->category_id,
                'play_session_id' => $entry->play_session_id,
                'amount' => $entry->amount,
                'reason' => $entry->reason,
                'created_at' => Iso::format($entry->created_at),
            ])->values()->all(),
            'sessions' => $sessions->map(fn (PlaySession $session): array => [
                'id' => $session->id,
                'member_id' => $session->member_id,
                'category' => $session->category?->slug,
                'status' => $session->status,
                'points' => $session->points,
                'target_points' => $session->target_points,
                'mistakes' => $session->mistakes,
                'second_chances_used' => $session->second_chances_used,
                'started_at' => Iso::format($session->started_at),
                'completed_at' => Iso::format($session->completed_at),
            ])->values()->all(),
            'goals' => $goalPlans->map(fn (GoalPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'mode' => $plan->mode,
                'period_type' => $plan->period_type,
                'target_points' => $plan->target_points,
                'reward_text' => $plan->reward_text,
                'starts_on' => $plan->starts_on->toDateString(),
                'ends_on' => $plan->ends_on?->toDateString(),
                'deleted' => $plan->deleted_at !== null,
                'participation' => $plan->planMembers->map(fn (GoalPlanMember $planMember): array => [
                    'member_id' => $planMember->member_id,
                    'target_override' => $planMember->target_override,
                ])->values()->all(),
            ])->values()->all(),
            'chat_messages' => $chatMessages->map(fn (ChatMessage $message): array => [
                'id' => $message->id,
                'family_id' => $message->family_id,
                'member_id' => $message->family_member_id,
                'body' => $message->body,
                'created_at' => Iso::format($message->created_at),
            ])->values()->all(),
            'consents' => $consents->map(fn (Consent $consent): array => [
                'type' => $consent->type,
                'version' => $consent->version,
                'subject_member_id' => $consent->subject_member_id,
                'accepted_at' => Iso::format($consent->accepted_at),
            ])->values()->all(),
            'devices' => $devices->map(fn (Device $device): array => [
                'name' => $device->name,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'has_push_token' => $device->push_token !== null,
                'last_seen_at' => Iso::format($device->last_seen_at),
                'created_at' => Iso::format($device->created_at),
            ])->values()->all(),
            'social_accounts' => $socialAccounts->map(fn (SocialAccount $account): array => [
                'provider' => $account->provider,
                'email' => $account->email,
                'linked_at' => Iso::format($account->linked_at),
            ])->values()->all(),
        ];
    }
}
