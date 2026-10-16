<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeleteFamilyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The family's current name, exactly. */
            'confirm' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $user = $this->user();
                $name = $user instanceof User ? $user->activeMembership()?->family->name : null;

                if ($name === null || $value !== $name) {
                    $fail('Type the family name exactly to confirm.');
                }
            }],
            /** Also delete my (the owner's) account. Default false: the owner keeps an account without a family. */
            'delete_my_account' => ['sometimes', 'boolean'],
        ];
    }
}
