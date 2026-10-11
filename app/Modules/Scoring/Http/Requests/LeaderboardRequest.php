<?php

namespace App\Modules\Scoring\Http\Requests;

use App\Modules\Scoring\Actions\BuildLeaderboard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaderboardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(BuildLeaderboard::PERIODS)],
        ];
    }
}
