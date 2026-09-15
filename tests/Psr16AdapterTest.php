<?php

declare(strict_types=1);

namespace LiteCache\Tests;

use LiteCache\Adapter\Psr16Adapter;
use LiteCache\CacheManager;
use LiteCache\Drivers\MemoryDriver;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Psr16AdapterTest extends TestCase
{
    private Psr16Adapter $psr16;

    protected function setUp(): void
    {
        $cache = new MemoryDriver();
        $this->psr16 = new Psr16Adapter($cache);
    }

    public function testImplementsPsr16Interface(): void
    {
        $this->assertInstanceOf(Psr16CacheInterface::class, $this->psr16);
    }

    public function testBasicGetSetDeleteHas(): void
    {
        $this->assertNull($this->psr16->get('foo'));
        $this->assertEquals('bar', $this->psr16->get('foo', 'bar'));
        $this->assertFalse($this->psr16->has('foo'));

        $this->assertTrue($this->psr16->set('foo', 'baz', 60));
        $this->assertTrue($this->psr16->has('foo'));
        $this->assertEquals('baz', $this->psr16->get('foo'));

        $this->assertTrue($this->psr16->delete('foo'));
        $this->assertFalse($this->psr16->has('foo'));
    }

    public function testMultipleOperations(): void
    {
        $data = [
            'item_1' => 'Alpha',
            'item_2' => 'Beta',
            'item_3' => 'Gamma',
        ];

        $this->assertTrue($this->psr16->setMultiple($data, 300));

        $retrieved = $this->psr16->getMultiple(['item_1', 'item_3', 'item_missing'], 'default_val');
        $this->assertEquals('Alpha', $retrieved['item_1']);
        $this->assertEquals('Gamma', $retrieved['item_3']);
        $this->assertEquals('default_val', $retrieved['item_missing']);

        $this->assertTrue($this->psr16->deleteMultiple(['item_1', 'item_2']));
        $this->assertFalse($this->psr16->has('item_1'));
        $this->assertFalse($this->psr16->has('item_2'));
        $this->assertTrue($this->psr16->has('item_3'));
    }

    public function testClear(): void
    {
        $this->psr16->set('k1', 'v1');
        $this->psr16->set('k2', 'v2');
        $this->assertTrue($this->psr16->clear());
        $this->assertFalse($this->psr16->has('k1'));
        $this->assertFalse($this->psr16->has('k2'));
    }

    public function testInvalidKeysThrowPsrException(): void
    {
        // 1. Empty string key
        $this->expectException(InvalidArgumentException::class);
        $this->psr16->get('');
    }

    public function testReservedCharactersThrowPsrException(): void
    {
        // Reserved characters {}()/\@:
        $this->expectException(InvalidArgumentException::class);
        $this->psr16->set('invalid{key}', 'val');
    }

    public function testCacheManagerPsr16FactoryHelper(): void
    {
        $adapter = CacheManager::psr16();
        $this->assertInstanceOf(Psr16Adapter::class, $adapter);
        $adapter->set('from_manager', 'works', 10);
        $this->assertEquals('works', $adapter->get('from_manager'));
    }
}
