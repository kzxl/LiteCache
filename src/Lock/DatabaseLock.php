<?php

declare(strict_types=1);

namespace LiteCache\Lock;

use PDO;

/**
 * Database-backed atomic lock using PDO with atomic INSERT/UPDATE and expiration.
 */
class DatabaseLock extends AbstractLock
{
    private PDO $pdo;
    private string $table;
    private static array $initializedTables = [];

    public function __construct(PDO $pdo, string $table, string $name, int $seconds = 0, ?string $owner = null)
    {
        parent::__construct($name, $seconds, $owner);
        $this->pdo = $pdo;
        $this->table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $this->initTable();
    }

    public function acquire(): bool
    {
        $now = time();
        $expiresAt = $this->seconds > 0 ? $now + $this->seconds : null;

        $stmt = $this->pdo->prepare("SELECT owner, expires_at FROM {$this->table} WHERE name = :name");
        $stmt->execute([':name' => $this->name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            try {
                $ins = $this->pdo->prepare("INSERT INTO {$this->table} (name, owner, expires_at) VALUES (:name, :owner, :exp)");
                $ins->execute([
                    ':name' => $this->name,
                    ':owner' => $this->owner,
                    ':exp' => $expiresAt,
                ]);
                $this->acquired = true;
                return true;
            } catch (\Throwable $e) {
                // Another process acquired the lock first
                return false;
            }
        }

        // Check if existing lock is expired
        if ($row['expires_at'] !== null && (int)$row['expires_at'] < $now) {
            $upd = $this->pdo->prepare("UPDATE {$this->table} SET owner = :new_owner, expires_at = :exp WHERE name = :name AND owner = :old_owner");
            $upd->execute([
                ':new_owner' => $this->owner,
                ':exp' => $expiresAt,
                ':name' => $this->name,
                ':old_owner' => $row['owner'],
            ]);

            if ($upd->rowCount() > 0) {
                $this->acquired = true;
                return true;
            }
        }

        return false;
    }

    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $del = $this->pdo->prepare("DELETE FROM {$this->table} WHERE name = :name AND owner = :owner");
        $del->execute([
            ':name' => $this->name,
            ':owner' => $this->owner,
        ]);

        $this->acquired = false;
        return $del->rowCount() > 0;
    }

    private function initTable(): void
    {
        if (isset(self::$initializedTables[$this->table])) {
            return;
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                name TEXT PRIMARY KEY,
                owner TEXT NOT NULL,
                expires_at INTEGER NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                name VARCHAR(255) PRIMARY KEY,
                owner VARCHAR(255) NOT NULL,
                expires_at INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $this->pdo->exec($sql);
        self::$initializedTables[$this->table] = true;
    }
}
