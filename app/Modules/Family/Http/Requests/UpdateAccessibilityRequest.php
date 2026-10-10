<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Family\Support\Accessibility;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessibilityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return Accessibility::rules();
    }
}
