<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Family;

class UpdateFamily
{
    /**
     * @param  array{name?: string, timezone?: string}  $data
     */
    public function handle(Family $family, array $data): Family
    {
        $family->fill(array_intersect_key($data, array_flip(['name', 'timezone'])))->save();

        return $family;
    }
}
