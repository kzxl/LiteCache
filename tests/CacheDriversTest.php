<?php

declare(strict_types=1);

namespace LiteCache\Tests;

use DateInterval;
use LiteCache\Drivers\DatabaseDriver;
use LiteCache\Drivers\FileOpCacheDriver;
use LiteCache\Drivers\MemoryDriver;
use PDO;
use PHPUnit\Framework\TestCase;

final class CacheDriversTest extends TestCase
{
    private string $tempDir;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lite_cache_test_' . bin2hex(random_bytes(6));
        @mkdir($this->tempDir, 0777, true);

        $this->pdo = new PDO('sqlite::memory:');
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function testMemoryDriverBasicCrud(): void
    {
        $cache = new MemoryDriver();
        $this->runBasicCacheSuite($cache);
    }

    public function testFileOpCacheDriverBasicCrud(): void
    {
        $cache = new FileOpCacheDriver($this->tempDir);
        $this->runBasicCacheSuite($cache);
    }

    public function testDatabaseDriverBasicCrud(): void
    {
        $cache = new DatabaseDriver($this->pdo, 'test_cache');
        $this->runBasicCacheSuite($cache);
    }

    private function runBasicCacheSuite(mixed $cache): void
    {
        // 1. Get default
        $this->assertNull($cache->get('non_existent'));
        $this->assertEquals('default', $cache->get('non_existent', 'default'));
        $this->assertFalse($cache->has('non_existent'));

        // 2. Set and Get
        $this->assertTrue($cache->set('user_name', 'Phong Vo', 60));
        $this->assertTrue($cache->has('user_name'));
        $this->assertEquals('Phong Vo', $cache->get('user_name'));

        // 3. Array / Object data serialization
        $user = ['id' => 1, 'email' => 'phong@kzxl.com', 'roles' => ['admin']];
        $cache->set('user_1', $user, new DateInterval('PT10M'));
        $this->assertEquals($user, $cache->get('user_1'));

        // 4. Remember
        $counter = 0;
        $val1 = $cache->remember('cached_calc', 60, function () use (&$counter) {
            $counter++;
            return 42;
        });
        $val2 = $cache->remember('cached_calc', 60, function () use (&$counter) {
            $counter++;
            return 999;
        });
        $this->assertEquals(42, $val1);
        $this->assertEquals(42, $val2);
        $this->assertEquals(1, $counter);

        // 5. Increment & Decrement
        $cache->set('counter', 10);
        $this->assertEquals(12, $cache->increment('counter', 2));
        $this->assertEquals(7, $cache->decrement('counter', 5));

        // 6. Delete
        $this->assertTrue($cache->delete('user_name'));
        $this->assertNull($cache->get('user_name'));

        // 7. Clear
        $cache->set('k1', 'v1');
        $cache->set('k2', 'v2');
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->has('k1'));
        $this->assertFalse($cache->has('k2'));

        // 8. Tags & Tag Invalidation
        $tagged = $cache->tags(['reports', 'q3']);
        $tagged->set('report_a', 'Data A');
        $tagged->set('report_b', 'Data B');

        $cache->set('unrelated', 'Keep me');

        $this->assertEquals('Data A', $cache->get('report_a'));
        $this->assertTrue($tagged->flushTags());

        $this->assertNull($cache->get('report_a'));
        $this->assertNull($cache->get('report_b'));
        $this->assertEquals('Keep me', $cache->get('unrelated'));
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
