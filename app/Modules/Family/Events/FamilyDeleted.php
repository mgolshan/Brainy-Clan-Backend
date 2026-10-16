<?php

namespace App\Modules\Family\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The family was deleted (`DELETE /family`): every membership is `removed` and the row is marked
 * `deleted_at`. Dispatched inside the deletion transaction, so synchronous listeners clean up
 * atomically (Goals soft-deletes the family's goal plans in one query).
 */
class FamilyDeleted
{
    use Dispatchable;

    public function __construct(public readonly int $familyId) {}
}
