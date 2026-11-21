<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomCodeCardsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Active members of the family to issue cards for; default: every non-admin member. */
            'member_ids' => ['sometimes', 'nullable', 'array', 'min:1', 'max:100'],
            'member_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>|null
     */
    public function memberIds(): ?array
    {
        $ids = $this->validated('member_ids');

        return is_array($ids) ? array_values(array_map(fn ($id) => (int) $id, $ids)) : null;
    }
}
