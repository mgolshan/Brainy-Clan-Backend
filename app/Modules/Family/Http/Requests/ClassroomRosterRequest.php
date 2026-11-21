<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomRosterRequest extends FormRequest
{
    /** Students per call (docs/API.md "Classroom tools" → "Roster"). */
    public const int MAX_STUDENTS = 35;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $year = ['integer', 'min:1900', 'max:'.now()->year];

        return [
            /** The year most of the class was born; each student may override it. The birth month is taken as June. */
            'birth_year' => ['required', ...$year],
            'students' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            /** 1–40 characters (trimmed), unique in the family and in the list (case-insensitive). */
            'students.*.nickname' => ['required', 'string', 'min:1', 'max:40', 'distinct:ignore_case'],
            'students.*.birth_year' => ['sometimes', 'nullable', ...$year],
        ];
    }

    /**
     * Nicknames are trimmed before validation, so " Ava " and "Ava" are the same name.
     */
    protected function prepareForValidation(): void
    {
        $students = $this->input('students');

        if (! is_array($students)) {
            return;
        }

        $this->merge(['students' => array_map(
            fn ($student) => is_array($student) && is_string($student['nickname'] ?? null)
                ? [...$student, 'nickname' => trim($student['nickname'])]
                : $student,
            $students,
        )]);
    }
}
