<?php

declare(strict_types=1);

namespace LiteCache\Drivers;

use DateInterval;
use DateTimeImmutable;
use LiteCache\CacheInterface;
use PDO;

/**
 * Database-backed Cache Driver (SQLite / MySQL / PostgreSQL).
 * ACID-safe, persistent cache with TTL and tag indexation.
 */
class DatabaseDriver implements CacheInterface
{
    private PDO $pdo;
    private string $table;
    /** @var string[] */
    private array $activeTags = [];
    private bool $tableChecked = false;

    public function __construct(PDO $pdo, string $table = 'lite_cache')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    private function ensureTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            key_id VARCHAR(255) PRIMARY KEY,
            val_data TEXT NOT NULL,
            tags TEXT NULL,
            expires_at INTEGER NULL
        )";
        $this->pdo->exec($sql);
        $this->tableChecked = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare("SELECT val_data, expires_at FROM {$this->table} WHERE key_id = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $default;
        }

        if ($row['expires_at'] !== null && (int)$row['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return unserialize($row['val_data']);
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->ensureTable();

        $exp = $this->resolveExpiration($ttl);
        $serialized = serialize($value);
        $tagsJson = !empty($this->activeTags) ? json_encode(array_values($this->activeTags)) : null;

        // SQLite & MySQL compatible UPSERT
        $sql = "INSERT INTO {$this->table} (key_id, val_data, tags, expires_at)
                VALUES (:key, :val, :tags, :exp)
                ON CONFLICT(key_id) DO UPDATE SET
                    val_data = excluded.val_data,
                    tags = excluded.tags,
                    expires_at = excluded.expires_at";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':key'  => $key,
                ':val'  => $serialized,
                ':tags' => $tagsJson,
                ':exp'  => $exp,
            ]);
        } catch (\PDOException) {
            // Fallback for MySQL if ON CONFLICT syntax differs
            $mySql = "INSERT INTO {$this->table} (key_id, val_data, tags, expires_at)
                      VALUES (:key, :val, :tags, :exp)
                      ON DUPLICATE KEY UPDATE
                          val_data = VALUES(val_data),
                          tags = VALUES(tags),
                          expires_at = VALUES(expires_at)";
            $stmt = $this->pdo->prepare($mySql);
            return $stmt->execute([
                ':key'  => $key,
                ':val'  => $serialized,
                ':tags' => $tagsJson,
                ':exp'  => $exp,
            ]);
        }
    }

    public function delete(string $key): bool
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE key_id = :key");
        return $stmt->execute([':key' => $key]);
    }

    public function clear(): bool
    {
        $this->ensureTable();
        return $this->pdo->exec("DELETE FROM {$this->table}") !== false;
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

        $this->ensureTable();
        foreach ($this->activeTags as $tag) {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE tags LIKE :tag");
            $stmt->execute([':tag' => '%' . json_encode($tag) . '%']);
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
