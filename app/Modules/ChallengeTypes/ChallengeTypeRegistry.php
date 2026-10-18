<?php

namespace App\Modules\ChallengeTypes;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\Content\Models\ChallengeType as ChallengeTypeRecord;
use App\Modules\Core\Support\ReferenceCache;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Maps a type key to its plugin. Handlers come from config/challenge_types.php; a row in the
 * `challenge_types` table may point a key at another handler class or switch it off.
 */
class ChallengeTypeRegistry
{
    /** @var array<string, array{handler_class: string, is_active: bool}>|null */
    private ?array $records = null;

    /** @var array<string, ChallengeType> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    public static function flush(): void
    {
        ReferenceCache::flush(ReferenceCache::CHALLENGE_TYPES);

        if (app()->resolved(self::class)) {
            $registry = app(self::class);
            $registry->records = null;
            $registry->instances = [];
        }
    }

    /**
     * All known handler classes, keyed by type key.
     *
     * @return array<string, class-string<ChallengeType>>
     */
    public function handlers(): array
    {
        /** @var array<string, class-string<ChallengeType>> $handlers */
        $handlers = (array) config('challenge_types.handlers', []);

        foreach ($this->records() as $key => $record) {
            if (is_a($record['handler_class'], ChallengeType::class, true)) {
                $handlers[$key] = $record['handler_class'];
            }
        }

        return $handlers;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->handlers());
    }

    public function isActive(string $key): bool
    {
        return $this->has($key) && ($this->records()[$key]['is_active'] ?? true);
    }

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        return array_values(array_filter(array_keys($this->handlers()), fn (string $key) => $this->isActive($key)));
    }

    public function get(string $key): ChallengeType
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $class = $this->handlers()[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown challenge type [{$key}].");
        }

        /** @var ChallengeType $handler */
        $handler = $this->container->make($class);

        return $this->instances[$key] = $handler;
    }

    /**
     * @return array<string, array{handler_class: string, is_active: bool}>
     */
    private function records(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }

        /** @var array<string, array{handler_class: string, is_active: bool}> $records */
        $records = ReferenceCache::remember(ReferenceCache::CHALLENGE_TYPES, fn (): array => ChallengeTypeRecord::query()->get()
            ->mapWithKeys(fn (ChallengeTypeRecord $type) => [$type->key => ['handler_class' => $type->handler_class, 'is_active' => $type->is_active]])
            ->all());

        return $this->records = $records;
    }
}
