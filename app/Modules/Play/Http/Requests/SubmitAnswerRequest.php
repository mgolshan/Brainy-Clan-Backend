<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_item_id' => ['required', 'integer'],
            /** The answer; its shape depends on the challenge type (checked by the type's handler). At most 10 keys. */
            'response' => ['present', 'array', 'max:10'],
            /** Word search: the found words (at most 100 entries). */
            'response.found' => ['sometimes', 'array', 'max:100'],
            /** Sudoku: the grid rows (at most 16). */
            'response.grid' => ['sometimes', 'array', 'max:16'],
            'time_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }
}
