<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomConsentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The consent statement version shown to the teacher (`GET /legal` → `classroom_consent.version`); a stale one → 422. */
            'version' => ['required', 'string', 'max:40'],
        ];
    }
}
