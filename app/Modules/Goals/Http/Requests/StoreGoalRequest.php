<?php

namespace App\Modules\Goals\Http\Requests;

use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'mode' => ['required', 'string', Rule::in(GoalPlan::MODES)],
            'period_type' => ['required', 'string', Rule::in(GoalPlan::PERIOD_TYPES)],
            'target_points' => ['required', 'integer', 'min:1', 'max:10000'],
            'reward_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'category_ids' => ['required', 'array', 'min:1', 'max:200'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'members' => ['sometimes', 'nullable', 'array', 'max:50'],
            'members.*.member_id' => ['required', 'integer', 'distinct'],
            'members.*.target_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
