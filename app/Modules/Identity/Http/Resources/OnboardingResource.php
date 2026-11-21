<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Onboarding object (docs/API.md "Onboarding (FTUE)"): every step key with its `done_at` or
 * `null`, `dismissed_at`, and the flow the server picked from tier + role with its steps.
 *
 * @property array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>} $resource
 */
class OnboardingResource extends JsonResource
{
    /**
     * @return array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Every step key → ISO 8601 UTC `done_at`, or `null` when not done. */
            'steps' => $this->resource['steps'],
            /** When the user hid the "Getting started" checklist; `null` = shown. */
            'dismissed_at' => $this->resource['dismissed_at'],
            /** `owner` | `member` | `teacher` | `student`, from the tier and the member's role. */
            'flow' => $this->resource['flow'],
            /** The flow's checklist steps, in order. */
            'required_steps' => $this->resource['required_steps'],
            /** Extra steps the flow may offer (teacher: `co_admin`). */
            'optional_steps' => $this->resource['optional_steps'],
        ];
    }
}
