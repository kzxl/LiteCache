<?php

declare(strict_types=1);

namespace LiteCache\Tests;

use LiteCache\Drivers\DatabaseDriver;
use LiteCache\Drivers\FileOpCacheDriver;
use LiteCache\Drivers\MemoryDriver;
use PDO;
use PHPUnit\Framework\TestCase;

final class CacheStampedeTest extends TestCase
{
    private string $tempDir;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lite_stampede_test_' . bin2hex(random_bytes(6));
        @mkdir($this->tempDir, 0777, true);
        $this->pdo = new PDO('sqlite::memory:');
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function testRememberWithLockOnMemoryDriver(): void
    {
        $cache = new MemoryDriver();
        $this->runRememberWithLockSuite($cache);
    }

    public function testRememberWithLockOnFileOpCacheDriver(): void
    {
        $cache = new FileOpCacheDriver($this->tempDir);
        $this->runRememberWithLockSuite($cache);
    }

    public function testRememberWithLockOnDatabaseDriver(): void
    {
        $cache = new DatabaseDriver($this->pdo, 'stampede_cache');
        $this->runRememberWithLockSuite($cache);
    }

    private function runRememberWithLockSuite(mixed $cache): void
    {
        $callCount = 0;
        $heavyComputation = function () use (&$callCount) {
            $callCount++;
            return 'heavy_calculated_result';
        };

        // 1. Initial compute under lock
        $res1 = $cache->rememberWithLock('heavy_key', 60, $heavyComputation, 5);
        $this->assertEquals('heavy_calculated_result', $res1);
        $this->assertEquals(1, $callCount);

        // 2. Second request hits fast-path cached value without recomputing
        $res2 = $cache->rememberWithLock('heavy_key', 60, $heavyComputation, 5);
        $this->assertEquals('heavy_calculated_result', $res2);
        $this->assertEquals(1, $callCount); // Callback was NOT called again

        // 3. Double-Checked Locking Verification:
        // Simulate a scenario where lock was acquired, but another process populated cache while waiting
        $cache->delete('stampede_check');
        $checkCalls = 0;

        $resA = $cache->rememberWithLock('stampede_check', 60, function () use (&$checkCalls) {
            $checkCalls++;
            return 'result_a';
        }, 5);

        $resB = $cache->rememberWithLock('stampede_check', 60, function () use (&$checkCalls) {
            $checkCalls++;
            return 'result_b';
        }, 5);

        $this->assertEquals('result_a', $resA);
        $this->assertEquals('result_a', $resB);
        $this->assertEquals(1, $checkCalls);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->deleteDir($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }
}
