<?php

declare(strict_types=1);

namespace LiteCache\Tests;

use LiteCache\Drivers\DatabaseDriver;
use LiteCache\Drivers\FileOpCacheDriver;
use LiteCache\Drivers\MemoryDriver;
use LiteCache\Exception\LockTimeoutException;
use LiteCache\Lock\DatabaseLock;
use LiteCache\Lock\FileLock;
use LiteCache\Lock\MemoryLock;
use PDO;
use PHPUnit\Framework\TestCase;

final class LockTest extends TestCase
{
    private string $tempDir;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lite_lock_test_' . bin2hex(random_bytes(6));
        @mkdir($this->tempDir, 0777, true);
        $this->pdo = new PDO('sqlite::memory:');
    }

    protected function tearDown(): void
    {
        MemoryLock::clearAll();
        $this->deleteDir($this->tempDir);
    }

    public function testMemoryLockAcquireAndRelease(): void
    {
        $lock1 = new MemoryLock('order_100', 10);
        $lock2 = new MemoryLock('order_100', 10);

        $this->assertTrue($lock1->acquire());
        $this->assertTrue($lock1->isAcquired());

        // Lock 2 cannot acquire while Lock 1 holds it
        $this->assertFalse($lock2->acquire());
        $this->assertFalse($lock2->isAcquired());

        // Lock 1 releases
        $this->assertTrue($lock1->release());
        $this->assertFalse($lock1->isAcquired());

        // Now Lock 2 can acquire
        $this->assertTrue($lock2->acquire());
        $this->assertTrue($lock2->release());
    }

    public function testFileLockAcquireAndRelease(): void
    {
        $lock1 = new FileLock($this->tempDir, 'billing_invoice', 10);
        $lock2 = new FileLock($this->tempDir, 'billing_invoice', 10);

        $this->assertTrue($lock1->acquire());
        $this->assertFalse($lock2->acquire());

        $this->assertTrue($lock1->release());
        $this->assertTrue($lock2->acquire());
        $this->assertTrue($lock2->release());
    }

    public function testDatabaseLockAcquireAndRelease(): void
    {
        $lock1 = new DatabaseLock($this->pdo, 'test_locks', 'stock_sync', 10);
        $lock2 = new DatabaseLock($this->pdo, 'test_locks', 'stock_sync', 10);

        $this->assertTrue($lock1->acquire());
        $this->assertFalse($lock2->acquire());

        $this->assertTrue($lock1->release());
        $this->assertTrue($lock2->acquire());
        $this->assertTrue($lock2->release());
    }

    public function testLockGetHelperWithCallback(): void
    {
        $lock = new MemoryLock('calc_tax', 10);

        $result = $lock->get(function () {
            return 12345;
        });

        $this->assertEquals(12345, $result);
        $this->assertFalse($lock->isAcquired());

        // Lock should be completely released and re-acquirable
        $this->assertTrue($lock->acquire());
        $lock->release();
    }

    public function testLockBlockWithTimeout(): void
    {
        $lock1 = new MemoryLock('long_task', 10);
        $lock2 = new MemoryLock('long_task', 10);

        $lock1->acquire();

        // Lock 2 attempts to block for 0.1 second and fails with LockTimeoutException
        $this->expectException(LockTimeoutException::class);
        $lock2->block(1, function () {
            return 'never reached';
        });
    }

    public function testDriverFactoryLockIntegration(): void
    {
        $mem = new MemoryDriver();
        $file = new FileOpCacheDriver($this->tempDir);
        $db = new DatabaseDriver($this->pdo);

        $l1 = $mem->lock('task_a', 5);
        $this->assertInstanceOf(MemoryLock::class, $l1);
        $this->assertTrue($l1->acquire());
        $this->assertTrue($l1->release());

        $l2 = $file->lock('task_b', 5);
        $this->assertInstanceOf(FileLock::class, $l2);
        $this->assertTrue($l2->acquire());
        $this->assertTrue($l2->release());

        $l3 = $db->lock('task_c', 5);
        $this->assertInstanceOf(DatabaseLock::class, $l3);
        $this->assertTrue($l3->acquire());
        $this->assertTrue($l3->release());
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
