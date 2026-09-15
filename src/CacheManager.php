<?php

declare(strict_types=1);

namespace LiteCache;

use LiteCache\Drivers\DatabaseDriver;
use LiteCache\Drivers\FileOpCacheDriver;
use LiteCache\Drivers\MemoryDriver;
use PDO;

/**
 * Factory & Facade for LiteCache.
 */
class CacheManager
{
    private static ?CacheInterface $defaultInstance = null;

    public static function memory(): MemoryDriver
    {
        return new MemoryDriver();
    }

    public static function file(string $cacheDir): FileOpCacheDriver
    {
        return new FileOpCacheDriver($cacheDir);
    }

    public static function database(PDO $pdo, string $table = 'lite_cache'): DatabaseDriver
    {
        return new DatabaseDriver($pdo, $table);
    }

    public static function setDefault(CacheInterface $cache): void
    {
        self::$defaultInstance = $cache;
    }

    public static function getInstance(): CacheInterface
    {
        if (self::$defaultInstance === null) {
            self::$defaultInstance = new MemoryDriver();
        }
        return self::$defaultInstance;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->get($key, $default);
    }

    public static function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        return self::getInstance()->delete($key);
    }

    public static function remember(string $key, int|\DateInterval|null $ttl, callable $callback): mixed
    {
        return self::getInstance()->remember($key, $ttl, $callback);
    }

    public static function tags(string|array $tags): CacheInterface
    {
        return self::getInstance()->tags($tags);
    }

    public static function lock(string $name, int $seconds = 0, ?string $owner = null): \LiteCache\Lock\LockInterface
    {
        return self::getInstance()->lock($name, $seconds, $owner);
    }

    public static function rememberWithLock(string $key, int|\DateInterval|null $ttl, callable $callback, int $lockTimeoutSeconds = 5): mixed
    {
        return self::getInstance()->rememberWithLock($key, $ttl, $callback, $lockTimeoutSeconds);
    }

    public static function psr16(?CacheInterface $cache = null): \LiteCache\Adapter\Psr16Adapter
    {
        return new \LiteCache\Adapter\Psr16Adapter($cache ?? self::getInstance());
    }
}
