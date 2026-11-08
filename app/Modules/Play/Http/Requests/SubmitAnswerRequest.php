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
            /** Memory match: every pair of positions turned over, in order (at most 200). */
            'response.attempts' => ['sometimes', 'array', 'max:200'],
            /** Pattern sequence: the pad positions tapped, in order (at most 20). */
            'response.input' => ['sometimes', 'array', 'max:20'],
            /** Spot the difference: taps as [x, y], normalized 0–1 within the tapped picture (at most 60). */
            'response.taps' => ['sometimes', 'array', 'max:60'],
            /** Cross Math: [row, col, value] per blank (at most one per blank; at most 50). */
            'response.fills' => ['sometimes', 'array', 'max:50'],
            /** Maze: every cell walked through, in order, as [row, col] (at most rows × cols × 4, 1024). */
            'response.path' => ['sometimes', 'array', 'max:1024'],
            /** Code Breaker: every guess, in order, as peg positions (at most max_guesses, 20). */
            'response.guesses' => ['sometimes', 'array', 'max:20'],
            'time_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }
}
