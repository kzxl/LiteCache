# LiteCache

[![Latest Version](https://img.shields.io/github/v/release/kzxl/LiteCache?label=version&color=blue)](https://github.com/kzxl/LiteCache/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

An ultra-lightweight, zero-dependency, high-performance Caching engine for **PHP 8.2+** with native OpCache, SQLite/MySQL, In-Memory drivers, and Tagged invalidation.

Part of the **LitePlatform** sovereign software suite (< 10MB RAM, zero 3rd-party vendor lock-in).

---

## 📦 Installation

### Option 1: Standard Composer (via Packagist)
```bash
composer require kzxl/lite-cache
```

### Option 2: Direct from Git Repository (VCS)
To pull directly from the official GitHub repository without waiting for Packagist synchronization, add the VCS repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kzxl/LiteCache.git"
        }
    ],
    "require": {
        "kzxl/lite-cache": "^1.2.0"
    }
}
```
Or configure via CLI:
```bash
composer config repositories.lite-cache vcs https://github.com/kzxl/LiteCache.git
composer require kzxl/lite-cache:^1.2.0
```

### Option 3: Local Path Repository (Monorepo / Development)
For local development where changes should reflect immediately via symlink:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../libs/LiteCache",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "kzxl/lite-cache": "@dev"
    }
}
```

---

## ⚡ Key Features

- **Zero 3rd-Party Dependencies**: Pure PHP 8.2+ without requiring `ext-redis` or external cache daemons.
- **Cache Stampede Defense (`rememberWithLock`)**: Double-checked locking prevents the Dogpile effect under heavy concurrent loads.
- **Atomic Locks / Mutex**: Multi-process safe locks via `FileLock` (`flock`), `DatabaseLock` (PDO SQLite/MySQL atomic upsert), and `MemoryLock`.
- **PSR-16 SimpleCache Compliant**: Native `Psr16Adapter` wrapping any LiteCache driver for seamless integration with PSR-16 consumers.
- **OpCache Shared Memory Accelerator**: `FileOpCacheDriver` stores cache directly as executable PHP files (`return [...]`), allowing PHP's native OpCache to hold structures in RAM for microsecond access.
- **Database Persistence**: `DatabaseDriver` stores tagged caches inside SQLite or MySQL with automatic TTL expiration and atomic locks.
- **In-Memory Speed**: `MemoryDriver` for CLI Daemons (`socket.php`, queue workers).
- **Tag-Based Invalidation**: Invalidate grouped cache entries easily (`$cache->tags(['monsters', 'zone_1'])->flushTags()`).

---

## 🚀 Quick Start

### 1. Cache Stampede Defense (`rememberWithLock`)
When a popular cache key expires under 10,000 requests/sec, `rememberWithLock` ensures only **1 process computes the result** while others wait and retrieve the freshly cached value without crashing your database:

```php
use LiteCache\CacheManager;

$cache = CacheManager::file(__DIR__ . '/../storage/cache');

$data = $cache->rememberWithLock('hot_catalog_report', 3600, function () use ($db) {
    return $db->query("SELECT heavy_aggregations FROM Sales")->fetchAll();
}, lockTimeoutSeconds: 5);
```

### 2. Standalone Atomic Lock (Distributed Task Coordination)
```php
$lock = $cache->lock('process_monthly_payroll', seconds: 60);

// Acquire and execute task with automatic release
$result = $lock->get(function () {
    // Critical Section: executed exclusively by 1 worker at a time
    return runPayroll();
});

// Or blocking with timeout
if ($lock->block(timeoutSeconds: 10)) {
    try {
        // Do critical work
    } finally {
        $lock->release();
    }
}
```

### 3. PSR-16 SimpleCache Standard Adapter
```php
use LiteCache\CacheManager;

// Wrap LiteCache into PSR-16 CacheInterface
$psr16 = CacheManager::psr16();

$psr16->set('user_profile_1', ['name' => 'Phong Vo'], 300);
$profile = $psr16->get('user_profile_1');
$batch = $psr16->getMultiple(['user_profile_1', 'user_profile_2']);
```

### 4. Tagged Cache Invalidation
```php
// Tag items by feature and user ID
$cache->tags(['inventory', 'user_123'])->set('bag', $bagItems, 1800);

// Invalidate all inventory caches across all users
$cache->tags(['inventory'])->flushTags();
```

### 5. SQLite / MySQL Database Cache
```php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/cache.sqlite');
$cache = CacheManager::database($pdo, table: 'app_cache');

$cache->set('exchange_rates', $rates, ttl: 86400);
```

---

## 🧪 Testing

```bash
composer install
vendor/bin/phpunit
```

---

## 📄 License

MIT License — Copyright (c) 2026 Phong Vo (kzxl).
