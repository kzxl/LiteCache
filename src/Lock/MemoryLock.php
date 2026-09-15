<?php

declare(strict_types=1);

namespace LiteCache\Lock;

/**
 * In-memory process lock.
 */
class MemoryLock extends AbstractLock
{
    /** @var array<string, array{owner: string, expires_at: float|null}> */
    private static array $locks = [];

    public function acquire(): bool
    {
        $now = microtime(true);

        if (isset(self::$locks[$this->name])) {
            $lock = self::$locks[$this->name];
            // Check if lock has expired
            if ($lock['expires_at'] !== null && $lock['expires_at'] < $now) {
                unset(self::$locks[$this->name]);
            } else {
                return false;
            }
        }

        self::$locks[$this->name] = [
            'owner' => $this->owner,
            'expires_at' => $this->seconds > 0 ? $now + $this->seconds : null,
        ];

        $this->acquired = true;
        return true;
    }

    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        if (isset(self::$locks[$this->name]) && self::$locks[$this->name]['owner'] === $this->owner) {
            unset(self::$locks[$this->name]);
            $this->acquired = false;
            return true;
        }

        $this->acquired = false;
        return false;
    }

    public static function clearAll(): void
    {
        self::$locks = [];
    }
}
