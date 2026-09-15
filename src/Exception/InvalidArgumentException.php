<?php

declare(strict_types=1);

namespace LiteCache\Exception;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

class InvalidArgumentException extends CacheException implements PsrInvalidArgumentException
{
}
