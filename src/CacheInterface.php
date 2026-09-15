<?php

declare(strict_types=1);

namespace LiteCache;

use DateInterval;

/**
 * Standard Cache Interface for Sovereign LiteCache.
 */
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function has(string $key): bool;

    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed;

    public function increment(string $key, int $value = 1): int|bool;

    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Scope cache operations to one or more tags.
     *
     * @param string|string[] $tags
     */
    public function tags(string|array $tags): self;

    /**
     * Flush all items associated with currently active tags.
     */
    public function flushTags(): bool;
}
