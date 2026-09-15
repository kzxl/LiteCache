<?php

declare(strict_types=1);

namespace LiteCache\Drivers;

use DateInterval;
use DateTimeImmutable;
use LiteCache\CacheInterface;

/**
 * High-performance File & OpCache Cache Driver.
 * Stores cache as compiled PHP arrays leveraging PHP's native shared-memory OpCache.
 */
class FileOpCacheDriver implements CacheInterface
{
    private string $cacheDir;
    /** @var string[] */
    private array $activeTags = [];

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) {
            return $default;
        }

        $data = @include $path;
        if (!is_array($data) || !array_key_exists('val', $data)) {
            @unlink($path);
            return $default;
        }

        if ($data['exp'] !== null && $data['exp'] < time()) {
            @unlink($path);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            return $default;
        }

        return unserialize($data['val']);
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $path = $this->getFilePath($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $exp = $this->resolveExpiration($ttl);
        $payload = [
            'exp'  => $exp,
            'tags' => $this->activeTags,
            'val'  => serialize($value),
        ];

        $code = "<?php\n// LiteCache Entry\nreturn " . var_export($payload, true) . ";\n";
        $tmpPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmpPath, $code, LOCK_EX) === false) {
            return false;
        }

        $success = @rename($tmpPath, $path);
        if ($success && function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        // Store tag index for fast invalidation
        if (!empty($this->activeTags)) {
            $this->indexTags($key, $this->activeTags);
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        $path = $this->getFilePath($key);
        if (file_exists($path)) {
            @unlink($path);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            return true;
        }
        return false;
    }

    public function clear(): bool
    {
        $this->deleteDirectoryContents($this->cacheDir);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
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

        $tagDir = $this->cacheDir . '/_tags';
        if (!is_dir($tagDir)) {
            return false;
        }

        foreach ($this->activeTags as $tag) {
            $indexFile = $tagDir . '/' . md5($tag) . '.idx';
            if (file_exists($indexFile)) {
                $keys = file($indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($keys as $k) {
                    $this->delete(trim($k));
                }
                @unlink($indexFile);
            }
        }
        return true;
    }

    private function getFilePath(string $key): string
    {
        $hash = sha1($key);
        return $this->cacheDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.php';
    }

    private function indexTags(string $key, array $tags): void
    {
        $tagDir = $this->cacheDir . '/_tags';
        if (!is_dir($tagDir)) {
            @mkdir($tagDir, 0777, true);
        }

        foreach ($tags as $tag) {
            $indexFile = $tagDir . '/' . md5($tag) . '.idx';
            @file_put_contents($indexFile, $key . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    private function deleteDirectoryContents(string $dir): void
    {
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->deleteDirectoryContents($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
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
