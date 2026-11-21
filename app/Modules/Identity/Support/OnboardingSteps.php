<?php

namespace App\Modules\Identity\Support;

/**
 * The onboarding (FTUE) step catalog (docs/API.md "Onboarding (FTUE)"): every step key, in the
 * order of the Onboarding object, split into **manual** steps (set by `POST /me/onboarding/{step}`)
 * and **derived** steps (computed by the server from real data, stored the first time they are true).
 * The flows say which steps a user's checklist shows; the server picks the flow from tier + role.
 */
final class OnboardingSteps
{
    public const string FLOW_OWNER = 'owner';

    public const string FLOW_MEMBER = 'member';

    public const string FLOW_TEACHER = 'teacher';

    public const string FLOW_STUDENT = 'student';

    /** Steps the app marks done. */
    public const array MANUAL = [
        'welcome',
        'pick_world',
        'welcome_family',
        'avatar_world',
        'family_goal',
        'classroom_welcome',
        'chat_mode',
        'end_of_year',
        'tip_play',
        'tip_goals',
        'tip_chat',
        'upgrade_family',
        'upgrade_big_clan',
        'upgrade_classroom',
    ];

    /** Steps the server derives from real data (a POST of one is 422). */
    public const array DERIVED = [
        'create_family',
        'add_member',
        'co_admin',
        'first_game',
        'first_goal',
        'say_hi',
        'class_details',
        'roster',
        'code_cards',
        'code_sign_in',
    ];

    /** Every key, in the order of the Onboarding object. */
    public const array ALL = [
        'welcome',
        'create_family',
        'pick_world',
        'add_member',
        'co_admin',
        'first_game',
        'first_goal',
        'say_hi',
        'welcome_family',
        'avatar_world',
        'family_goal',
        'classroom_welcome',
        'class_details',
        'roster',
        'code_cards',
        'chat_mode',
        'end_of_year',
        'code_sign_in',
        'tip_play',
        'tip_goals',
        'tip_chat',
        'upgrade_family',
        'upgrade_big_clan',
        'upgrade_classroom',
    ];

    public static function isManual(string $step): bool
    {
        return in_array($step, self::MANUAL, true);
    }

    public static function isDerived(string $step): bool
    {
        return in_array($step, self::DERIVED, true);
    }
}
