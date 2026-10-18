<?php

namespace App\Modules\Core\Contracts;

/**
 * A model whose rows feed a ReferenceCache. Put `#[UseEloquentBuilder(ReferenceDataBuilder::class)]`
 * on the model so every Eloquent write calls flushReferenceCaches().
 */
interface InvalidatesReferenceCache
{
    /**
     * Invalidate every cache built from this table (after the current transaction commits).
     */
    public static function flushReferenceCaches(): void;
}
