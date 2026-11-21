<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Family\Actions\BuildClassroomSummary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomSummaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** `season` (default): from the season start, or everything without a season; `all`: everything. */
            'period' => ['sometimes', 'string', Rule::in(BuildClassroomSummary::PERIODS)],
        ];
    }

    public function period(): string
    {
        return (string) $this->string('period', BuildClassroomSummary::PERIOD_SEASON);
    }
}
