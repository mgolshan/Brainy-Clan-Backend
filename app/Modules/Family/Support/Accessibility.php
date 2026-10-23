<?php

namespace App\Modules\Family\Support;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\Models\AgeBand;
use Illuminate\Validation\Rule;

/**
 * A member's accessibility settings: stored values on top of the age band's defaults.
 * `theme: null` means "use the policy's ui_theme"; `sound_effects: null` means "use the policy's
 * accessibility_defaults.sound_effects" (both are returned as stored, the app resolves them).
 */
final class Accessibility
{
    /**
     * @return array{text_scale: float, reduced_motion: bool, calm_timers: bool, theme: string|null, sound_effects: bool|null}
     */
    public static function forMember(FamilyMember $member, ?AgeBand $band): array
    {
        $defaults = (array) ($band?->rule('accessibility_defaults') ?? []);
        $stored = $member->accessibility ?? [];

        return [
            'text_scale' => (float) ($stored['text_scale'] ?? $defaults['text_scale'] ?? 1.0),
            'reduced_motion' => (bool) ($stored['reduced_motion'] ?? $defaults['reduced_motion'] ?? false),
            'calm_timers' => (bool) ($stored['calm_timers'] ?? $defaults['calm_timers'] ?? false),
            'theme' => isset($stored['theme']) && is_string($stored['theme']) ? $stored['theme'] : null,
            'sound_effects' => isset($stored['sound_effects']) ? (bool) $stored['sound_effects'] : null,
        ];
    }

    /**
     * Validation rules, optionally nested under a prefix (e.g. "accessibility.").
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'text_scale' => ['sometimes', 'numeric', 'min:0.8', 'max:1.6'],
            $prefix.'reduced_motion' => ['sometimes', 'boolean'],
            $prefix.'calm_timers' => ['sometimes', 'boolean'],
            $prefix.'theme' => ['sometimes', 'nullable', 'string', Rule::in(AgeBand::THEMES)],
            $prefix.'sound_effects' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Merge validated changes into the stored settings.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public static function merge(?array $stored, array $changes): array
    {
        $allowed = array_intersect_key($changes, array_flip(['text_scale', 'reduced_motion', 'calm_timers', 'theme', 'sound_effects']));

        foreach (['reduced_motion', 'calm_timers'] as $flag) {
            if (array_key_exists($flag, $allowed)) {
                $allowed[$flag] = (bool) $allowed[$flag];
            }
        }

        if (array_key_exists('sound_effects', $allowed) && $allowed['sound_effects'] !== null) {
            $allowed['sound_effects'] = (bool) $allowed['sound_effects'];
        }

        if (array_key_exists('text_scale', $allowed)) {
            $allowed['text_scale'] = round((float) $allowed['text_scale'], 2);
        }

        return array_replace($stored ?? [], $allowed);
    }
}
