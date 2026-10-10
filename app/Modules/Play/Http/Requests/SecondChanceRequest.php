<?php

namespace App\Modules\Play\Http\Requests;

use App\Modules\Play\Models\SecondChance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SecondChanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in([SecondChance::METHOD_FREE, SecondChance::METHOD_AD])],
            'reward_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
