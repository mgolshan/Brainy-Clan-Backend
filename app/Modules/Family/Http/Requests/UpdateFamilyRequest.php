<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Family\Models\Family;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            /** The chat's bad-words filter (every plan, on by default). Affects new messages only. */
            'chat_filter' => ['sometimes', 'boolean'],
            /** Classroom only (`policy.chat.modes_allowed`), else 403 `plan_required`. */
            'chat_mode' => ['sometimes', 'string', Rule::in(Family::CHAT_MODES)],
        ];
    }
}
