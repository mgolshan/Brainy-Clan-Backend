<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\OnboardingSteps;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /me/onboarding/{step}`: the `{step}` path segment must be a **manual** step key; unknown
 * and derived keys are 422 `validation_failed` on `step`. No body.
 */
class MarkOnboardingStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return ['step' => $this->route('step')];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'step' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (OnboardingSteps::isDerived((string) $value)) {
                    $fail('This step is done automatically.');
                } elseif (! OnboardingSteps::isManual((string) $value)) {
                    $fail('Unknown onboarding step.');
                }
            }],
        ];
    }

    public function step(): string
    {
        return (string) $this->route('step');
    }
}
