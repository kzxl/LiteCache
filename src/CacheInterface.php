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

    /**
     * Get an atomic lock instance for coordinating concurrent tasks.
     *
     * @param string $name Lock identifier
     * @param int $seconds Lock expiration TTL in seconds (0 = indefinite until released)
     * @param string|null $owner Optional custom owner token
     */
    public function lock(string $name, int $seconds = 0, ?string $owner = null): \LiteCache\Lock\LockInterface;

    /**
     * Get an item from cache or compute it under an atomic lock to prevent Cache Stampede (Dogpile Effect).
     *
     * @param string $key
     * @param int|DateInterval|null $ttl
     * @param callable(): mixed $callback
     * @param int $lockTimeoutSeconds
     */
    public function rememberWithLock(string $key, int|DateInterval|null $ttl, callable $callback, int $lockTimeoutSeconds = 5): mixed;
}
