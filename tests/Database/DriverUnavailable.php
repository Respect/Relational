<?php

declare(strict_types=1);

namespace Respect\Relational\Database;

use RuntimeException;

final class DriverUnavailable extends RuntimeException
{
    public function __construct(public readonly string $driver)
    {
        parent::__construct('PDO driver "' . $driver . '" is not available');
    }
}
