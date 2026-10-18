<?php

namespace App\Modules\Core\Support;

use Closure;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one way to cache admin-maintained reference data (docs/PLAN.md "Caching"): age bands +
 * settings, the category tree, challenge type records and published-content availability.
 *
 * Keys are versioned instead of deleted: `ref:{name}:version` holds a random token and the value
 * lives under `ref:{name}:{token}`. A write never deletes the value, it replaces the token
 * **after the transaction commits** (ReferenceDataBuilder calls flush() on every write). So:
 *
 * - a reader that fetched the old token before the commit can only store under the old,
 *   abandoned key — the classic "read old row, write it back after the forget" race cannot
 *   leave stale data behind;
 * - values computed inside an open transaction are returned but never stored (the transaction
 *   may see an older snapshot or roll back);
 * - a lost token (Redis restart, eviction) just means a new token and a cache miss.
 *
 * No cache tags: a tag flush is not atomic with the writes either, and versioned keys work on
 * every store (the test suite uses the array store with serialization, like Redis).
 */
final class ReferenceCache
{
    /** Age bands + settings (PolicyResolver). */
    public const string POLICY = 'policy.v2';

    /** Every category row (CategoryTree). */
    public const string CATEGORY_TREE = 'category-tree.v2';

    /** `challenge_types` rows (ChallengeTypeRegistry). */
    public const string CHALLENGE_TYPES = 'challenge-types.v2';

    /** Distinct category/type/locale/age combinations of published content (ContentAvailability). */
    public const string CONTENT_AVAILABILITY = 'content-availability.v1';

    public const array ALL = [self::POLICY, self::CATEGORY_TREE, self::CHALLENGE_TYPES, self::CONTENT_AVAILABILITY];

    /** Abandoned versions expire on their own; the current one is simply recomputed after a week. */
    private const int TTL_SECONDS = 7 * 24 * 3600;

    /**
     * The cached value, or `$compute()` (stored unless a transaction is open).
     *
     * @template TValue of array
     *
     * @param  Closure(): TValue  $compute  must return plain arrays/scalars (no objects: the
     *                                      store unserializes without allowed classes)
     * @return TValue
     */
    public static function remember(string $name, Closure $compute): array
    {
        $key = self::key($name);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            /** @var TValue $cached */
            return $cached;
        }

        $value = $compute();

        if (! self::insideTransaction()) {
            Cache::put($key, $value, self::TTL_SECONDS);
        }

        return $value;
    }

    /**
     * Invalidate after the current transaction commits (immediately when there is none).
     * A rolled-back transaction leaves the cache untouched, which is correct.
     */
    public static function flush(string ...$names): void
    {
        foreach ($names as $name) {
            DB::afterCommit(fn () => Cache::forever(self::versionKey($name), self::newToken()));
        }
    }

    /**
     * The key the current value of `$name` is stored under.
     */
    public static function key(string $name): string
    {
        $versionKey = self::versionKey($name);
        $token = Cache::get($versionKey);

        if (! is_string($token)) {
            Cache::add($versionKey, self::newToken(), null);
            $token = (string) Cache::get($versionKey);
        }

        return "ref:{$name}:{$token}";
    }

    private static function versionKey(string $name): string
    {
        return "ref:{$name}:version";
    }

    private static function newToken(): string
    {
        return Str::random(16);
    }

    /**
     * An application transaction is open (the test suite's wrapping transaction does not count).
     */
    private static function insideTransaction(): bool
    {
        $manager = app('db.transactions');

        return $manager instanceof DatabaseTransactionsManager
            ? $manager->callbackApplicableTransactions()->isNotEmpty()
            : DB::transactionLevel() > 0;
    }
}
