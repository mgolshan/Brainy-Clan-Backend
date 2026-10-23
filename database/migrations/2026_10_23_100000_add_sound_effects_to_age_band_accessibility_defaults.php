<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (theme worlds, 2026-10-23): every age band gets
     * `policy.accessibility_defaults.sound_effects` — on for child bands, off for the others.
     * A value an admin already set is kept. Member settings need nothing: they live in the
     * `family_members.accessibility` JSON, where a missing `sound_effects` means "policy default".
     * Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    public function up(): void
    {
        DB::table('age_bands')->orderBy('id')->get(['id', 'code', 'policy'])->each(function (object $band): void {
            $policy = json_decode((string) $band->policy, true) ?: [];

            if (array_key_exists('sound_effects', (array) ($policy['accessibility_defaults'] ?? []))) {
                return;
            }

            $isChild = $band->code === 'child' || (bool) ($policy['is_child'] ?? false);
            $policy['accessibility_defaults'] = [...(array) ($policy['accessibility_defaults'] ?? []), 'sound_effects' => $isChild];

            DB::table('age_bands')->where('id', $band->id)->update(['policy' => json_encode($policy)]);
        });
    }

    public function down(): void
    {
        DB::table('age_bands')->orderBy('id')->get(['id', 'policy'])->each(function (object $band): void {
            $policy = json_decode((string) $band->policy, true) ?: [];
            unset($policy['accessibility_defaults']['sound_effects']);

            DB::table('age_bands')->where('id', $band->id)->update(['policy' => json_encode($policy)]);
        });
    }
};
