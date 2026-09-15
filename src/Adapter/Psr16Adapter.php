<?php

declare(strict_types=1);

namespace LiteCache\Adapter;

use DateInterval;
use LiteCache\CacheInterface;
use LiteCache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Traversable;

/**
 * PSR-16 SimpleCache specification adapter wrapping sovereign LiteCache instances.
 */
class Psr16Adapter implements Psr16CacheInterface
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return $this->cache->get($key, $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $this->validateKey($key);
            $result[$key] = $this->cache->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $this->validateKey((string)$key);
            if (!$this->cache->set((string)$key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $this->validateKey($key);
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        return $this->cache->has($key);
    }

    public function getUnderlyingCache(): CacheInterface
    {
        return $this->cache;
    }

    private function validateKey(mixed $key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be a string, %s given.', get_debug_type($key)));
        }

        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty.');
        }

        if (preg_match('/[{}()\/\\\\@:]/', $key)) {
            throw new InvalidArgumentException(sprintf('Cache key "%s" contains reserved characters {}()/\@:.', $key));
        }
    }
}
