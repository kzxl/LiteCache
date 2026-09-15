<?php

declare(strict_types=1);

namespace LiteCache\Lock;

/**
 * File-based atomic lock using flock() with non-blocking exclusive locks.
 */
class FileLock extends AbstractLock
{
    private string $lockDir;
    /** @var resource|null */
    private $handle = null;

    public function __construct(string $lockDir, string $name, int $seconds = 0, ?string $owner = null)
    {
        parent::__construct($name, $seconds, $owner);
        $this->lockDir = rtrim($lockDir, '/\\');
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0777, true);
        }
    }

    public function acquire(): bool
    {
        $path = $this->getFilePath();
        $handle = @fopen($path, 'c+');

        if (!$handle) {
            return false;
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return false;
        }

        // Lock acquired via OS flock: write metadata
        @ftruncate($handle, 0);
        @rewind($handle);
        @fwrite($handle, json_encode([
            'owner' => $this->owner,
            'expires_at' => $this->seconds > 0 ? time() + $this->seconds : null,
        ]));
        @fflush($handle);

        $this->handle = $handle;
        $this->acquired = true;
        return true;
    }

    public function release(): bool
    {
        if (!$this->acquired || !$this->handle) {
            return false;
        }

        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
        $this->acquired = false;

        $path = $this->getFilePath();
        @unlink($path);

        return true;
    }

    private function getFilePath(): string
    {
        return $this->lockDir . '/' . sha1($this->name) . '.lock';
    }
}
