<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\Device;
use Illuminate\Validation\Rule;

/**
 * Every auth call takes `device_name` and `platform` (docs/API.md "Auth").
 */
trait DeviceRules
{
    /**
     * @return array<string, mixed>
     */
    protected function deviceRules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:100'],
            'platform' => ['required', 'string', Rule::in(Device::PLATFORMS)],
        ];
    }

    public function appVersion(): ?string
    {
        $version = $this->header('X-App-Version');

        return is_string($version) && $version !== '' ? substr($version, 0, 20) : null;
    }

    /**
     * Birth year/month rules shared by sign-up and invites.
     *
     * @return array<string, mixed>
     */
    protected function birthRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'birth_year' => [$presence, 'integer', 'min:1900', 'max:'.now()->year],
            'birth_month' => [$presence, 'integer', 'min:1', 'max:12'],
        ];
    }
}
