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
        "kzxl/lite-cache": "^1.1.0"
    }
}
```
Or configure via CLI:
```bash
composer config repositories.lite-cache vcs https://github.com/kzxl/LiteCache.git
composer require kzxl/lite-cache:^1.1.0
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
- **OpCache Shared Memory Accelerator**: `FileOpCacheDriver` stores cache directly as executable PHP files (`return [...]`), allowing PHP's native OpCache to hold structures in RAM for microsecond access.
- **Database Persistence**: `DatabaseDriver` stores tagged caches inside SQLite or MySQL with automatic TTL expiration and atomic locks.
- **In-Memory Speed**: `MemoryDriver` for CLI Daemons (`socket.php`, queue workers).
- **Tag-Based Invalidation**: Invalidate grouped cache entries easily (`$cache->tags(['monsters', 'zone_1'])->flushTags()`).
- **Atomic Remember Pattern**: Compute expensive database queries once and cache transparently.

---

## 🚀 Quick Start

### 1. Memory Driver (CLI / WebSocket Servers)
```php
use LiteCache\CacheManager;

$cache = CacheManager::memory();
$cache->set('active_hero_count', 42, ttl: 300);
$count = $cache->get('active_hero_count'); // 42
```

### 2. OpCache Accelerated File Driver (Web APIs)
```php
use LiteCache\CacheManager;

$cache = CacheManager::file(__DIR__ . '/../storage/cache/data');

// Cache expensive queries transparently
$items = $cache->remember('master_items', 3600, function () use ($db) {
    return $db->query("SELECT * FROM Items")->fetchAll();
});
```

### 3. Tagged Cache Invalidation
```php
// Tag items by feature and user ID
$cache->tags(['inventory', 'user_123'])->set('bag', $bagItems, 1800);

// Invalidate all inventory caches across all users
$cache->tags(['inventory'])->flushTags();
```

### 4. SQLite / MySQL Database Cache
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
