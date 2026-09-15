<?php

declare(strict_types=1);

namespace LiteCache\Exception;

use Psr\SimpleCache\CacheException as PsrCacheException;
use RuntimeException;

class CacheException extends RuntimeException implements PsrCacheException
{
}
