<?php

namespace App\Modules\Goals\Http\Requests;

use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allCategories = $this->boolean('all_categories');

        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'mode' => ['sometimes', 'string', Rule::in(GoalPlan::MODES)],
            'period_type' => ['sometimes', 'string', Rule::in(GoalPlan::PERIOD_TYPES)],
            'target_points' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'reward_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_on' => ['sometimes', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'is_active' => ['sometimes', 'boolean'],
            /** `true`: any category counts (scopes cleared, `category_ids` must be absent or empty). `false` needs `category_ids` when the plan has no scopes. */
            'all_categories' => ['sometimes', 'boolean'],
            'category_ids' => $allCategories
                ? ['sometimes', 'nullable', 'array', 'max:0']
                : ['sometimes', 'array', 'min:1', 'max:200'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            /** Switching to `true` adds the missing active members to the plan and its open period right away. */
            'everyone' => ['sometimes', 'boolean'],
            'members' => ['sometimes', 'nullable', 'array', 'max:50'],
            'members.*.member_id' => ['required', 'integer', 'distinct'],
            'members.*.target_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
