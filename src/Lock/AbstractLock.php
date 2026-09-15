<?php

declare(strict_types=1);

namespace LiteCache\Lock;

use LiteCache\Exception\LockTimeoutException;

/**
 * Base abstract class providing common blocking, callback execution, and auto-release mechanics.
 */
abstract class AbstractLock implements LockInterface
{
    protected string $name;
    protected int $seconds;
    protected string $owner;
    protected bool $acquired = false;

    public function __construct(string $name, int $seconds = 0, ?string $owner = null)
    {
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?? bin2hex(random_bytes(16));
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    public function block(int $timeoutSeconds, ?callable $callback = null): mixed
    {
        $start = microtime(true);
        $timeout = (float)$timeoutSeconds;

        while (true) {
            if ($this->acquire()) {
                if ($callback !== null) {
                    try {
                        return $callback();
                    } finally {
                        $this->release();
                    }
                }
                return true;
            }

            if ((microtime(true) - $start) >= $timeout) {
                if ($callback !== null) {
                    throw new LockTimeoutException("Timed out after {$timeoutSeconds} seconds acquiring lock '{$this->name}'.");
                }
                return false;
            }

            // Sleep 25ms between attempts to avoid burning CPU
            usleep(25000);
        }
    }

    public function get(?callable $callback = null): mixed
    {
        if (!$this->acquire()) {
            if ($callback !== null) {
                throw new LockTimeoutException("Could not acquire lock '{$this->name}'.");
            }
            return false;
        }

        if ($callback !== null) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return true;
    }

    public function __destruct()
    {
        if ($this->acquired) {
            $this->release();
        }
    }
}
