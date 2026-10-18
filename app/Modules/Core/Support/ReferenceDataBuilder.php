<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Contracts\InvalidatesReferenceCache;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent builder for models whose rows are cached by ReferenceCache. Every write that runs
 * through Eloquent ends here — `save()`/`delete()` on a model (also inside `withoutEvents`,
 * `saveQuietly`, seeders), mass updates/deletes (Filament reorder and bulk actions), inserts,
 * upserts and increments — and invalidates the model's caches after it. Only raw
 * `DB::table(...)` writes and SQL run outside Laravel bypass it (use `reference-cache:flush`).
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ReferenceDataBuilder extends Builder
{
    public function update(array $values)
    {
        return $this->invalidatingAfter(fn () => parent::update($values));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return $this->invalidatingAfter(fn () => parent::upsert($values, $uniqueBy, $update));
    }

    public function touch($column = null)
    {
        return $this->invalidatingAfter(fn () => parent::touch($column));
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->invalidatingAfter(fn () => parent::increment($column, $amount, $extra));
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->invalidatingAfter(fn () => parent::decrement($column, $amount, $extra));
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        return $this->invalidatingAfter(fn () => parent::incrementEach($columns, $extra));
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        return $this->invalidatingAfter(fn () => parent::decrementEach($columns, $extra));
    }

    public function delete()
    {
        return $this->invalidatingAfter(fn () => parent::delete());
    }

    public function forceDelete()
    {
        return $this->invalidatingAfter(fn () => parent::forceDelete());
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function insert(array $values): bool
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->insert($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->insertGetId($values, $sequence));
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function insertOrIgnore(array $values): int
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->insertOrIgnore($values));
    }

    /**
     * @param  list<string>  $columns
     */
    public function insertUsing(array $columns, mixed $query): int
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->insertUsing($columns, $query));
    }

    /**
     * @param  list<string>  $columns
     */
    public function insertOrIgnoreUsing(array $columns, mixed $query): int
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->insertOrIgnoreUsing($columns, $query));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        return $this->invalidatingAfter(fn () => $this->toBase()->updateOrInsert($attributes, $values));
    }

    public function truncate(): void
    {
        $this->invalidatingAfter(function (): void {
            $this->toBase()->truncate();
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $write
     * @return TResult
     */
    private function invalidatingAfter(Closure $write): mixed
    {
        $result = $write();
        $model = $this->getModel();

        if ($model instanceof InvalidatesReferenceCache) {
            $model::flushReferenceCaches();
        }

        return $result;
    }
}
