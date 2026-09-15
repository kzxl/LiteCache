<?php

declare(strict_types=1);

namespace LiteCache\Drivers;

use DateInterval;
use DateTimeImmutable;
use LiteCache\CacheInterface;

/**
 * High-speed In-Memory Cache Driver.
 * Keeps cache in RAM for long-running processes (WebSockets, Workers, CLI).
 */
class MemoryDriver implements CacheInterface
{
    /** @var array<string, array{val: mixed, exp: ?int, tags: string[]}> */
    private array $storage = [];
    /** @var string[] */
    private array $activeTags = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];
        if ($item['exp'] !== null && $item['exp'] < time()) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['val'];
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $exp = $this->resolveExpiration($ttl);
        $this->storage[$key] = [
            'val'  => $value,
            'exp'  => $exp,
            'tags' => $this->activeTags,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $computed = $callback();
        $this->set($key, $computed, $ttl);
        return $computed;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        if (!is_numeric($current)) {
            return false;
        }
        $new = (int)$current + $value;
        $this->set($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function tags(string|array $tags): self
    {
        $clone = clone $this;
        $clone->activeTags = is_array($tags) ? $tags : [$tags];
        return $clone;
    }

    public function flushTags(): bool
    {
        if (empty($this->activeTags)) {
            return false;
        }

        foreach ($this->storage as $key => $item) {
            $matching = array_intersect($this->activeTags, $item['tags']);
            if (!empty($matching)) {
                unset($this->storage[$key]);
            }
        }
        return true;
    }

    private function resolveExpiration(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl)->getTimestamp();
        }
        return time() + $ttl;
    }
}
