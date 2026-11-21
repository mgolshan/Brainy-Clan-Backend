<?php

namespace App\Modules\Family\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ClassroomNewYearRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The class name, retyped to confirm (case and surrounding spaces ignored). */
            'confirm_name' => ['required', 'string', 'max:80'],
            /** What to clear; at least one flag must be true. */
            'clear' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) || ! in_array(true, array_map(fn ($flag) => filter_var($flag, FILTER_VALIDATE_BOOL), array_values(array_intersect_key($value, array_flip(['students', 'points', 'chat', 'goals'])))), true)) {
                    $fail('Choose at least one thing to clear.');
                }
            }],
            /** Remove every student (non-admin member). */
            'clear.students' => ['sometimes', 'boolean'],
            /** Start a new points season (nothing is deleted). */
            'clear.points' => ['sometimes', 'boolean'],
            /** Delete every chat message and reaction. */
            'clear.chat' => ['sometimes', 'boolean'],
            /** Delete every goal plan. */
            'clear.goals' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{students: bool, points: bool, chat: bool, goals: bool}
     */
    public function flags(): array
    {
        return [
            'students' => $this->boolean('clear.students'),
            'points' => $this->boolean('clear.points'),
            'chat' => $this->boolean('clear.chat'),
            'goals' => $this->boolean('clear.goals'),
        ];
    }
}
