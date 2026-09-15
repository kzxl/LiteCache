<?php

declare(strict_types=1);

namespace LiteCache\Lock;

/**
 * Contract for Atomic Lock / Mutex.
 */
interface LockInterface
{
    /**
     * Attempt to acquire the lock immediately (non-blocking).
     */
    public function acquire(): bool;

    /**
     * Release the lock. Returns true if released, false if not owned or already released.
     */
    public function release(): bool;

    /**
     * Attempt to acquire the lock, blocking/waiting up to $timeoutSeconds.
     * If a callback is provided, executes it while holding the lock and automatically releases it.
     *
     * @param int $timeoutSeconds Maximum seconds to wait to acquire the lock.
     * @param (callable(): mixed)|null $callback
     * @return mixed Boolean true if acquired without callback, or callback return value.
     */
    public function block(int $timeoutSeconds, ?callable $callback = null): mixed;

    /**
     * Acquire the lock, execute the callback, and release the lock automatically.
     *
     * @param (callable(): mixed)|null $callback
     * @return mixed Boolean true if acquired without callback, or callback return value.
     */
    public function get(?callable $callback = null): mixed;

    /**
     * Get the unique owner token for this lock instance.
     */
    public function owner(): string;

    /**
     * Check if lock is currently acquired by this instance.
     */
    public function isAcquired(): bool;
}
