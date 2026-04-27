<?php

declare(strict_types=1);

namespace Respect\Relational\Database;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $conn;

    protected string $driver;

    protected function setUp(): void
    {
        try {
            $this->conn = ConnectionFactory::create();
        } catch (DriverUnavailable $e) {
            $this->markTestSkipped($e->getMessage());
        }

        // Read the driver back from the live PDO instead of trusting the env,
        // so a misconfigured DB_DRIVER cannot push tests onto the wrong schema.
        $this->driver = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    protected function resetTables(string ...$names): void
    {
        foreach ($names as $name) {
            $this->conn->exec('DROP TABLE IF EXISTS ' . $name);
        }
    }

    /**
     * Resync Postgres IDENTITY sequences after fixtures with explicit IDs.
     * No-op for sqlite and mysql (sqlite picks max+1 automatically; mysql
     * advances AUTO_INCREMENT on explicit insert).
     *
     * @param array<string, string> $tables map of table name to id column name
     */
    protected function syncSequences(array $tables): void
    {
        if ($this->driver !== 'pgsql') {
            return;
        }

        foreach ($tables as $table => $idColumn) {
            $this->conn->exec(
                "SELECT setval(pg_get_serial_sequence('" . $table . "', '" . $idColumn . "'), "
                . 'COALESCE((SELECT MAX(' . $idColumn . ') FROM ' . $table . '), 1))',
            );
        }
    }
}
