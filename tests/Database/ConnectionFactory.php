<?php

declare(strict_types=1);

namespace Respect\Relational\Database;

use InvalidArgumentException;
use PDO;

use function getenv;
use function in_array;
use function strpos;
use function substr;

final class ConnectionFactory
{
    /**
     * Resolve the requested driver. The DSN scheme wins when DB_DSN is set,
     * since it is what PDO will actually use; DB_DRIVER is then validated
     * against it. Falls back to DB_DRIVER alone, then to sqlite.
     *
     * @throws InvalidDriverConfiguration when DB_DRIVER and DB_DSN disagree.
     */
    public static function driver(): string
    {
        $envDriver = self::env('DB_DRIVER');
        $envDsn = self::env('DB_DSN');

        if ($envDsn !== null) {
            $dsnDriver = self::extractDsnScheme($envDsn);
            if ($envDriver !== null && $envDriver !== $dsnDriver) {
                throw new InvalidDriverConfiguration(
                    'DB_DRIVER (' . $envDriver . ') does not match the scheme of DB_DSN (' . $dsnDriver . ')',
                );
            }

            return $dsnDriver;
        }

        return $envDriver ?? 'sqlite';
    }

    /**
     * @throws DriverUnavailable
     * @throws InvalidDriverConfiguration
     */
    public static function create(): PDO
    {
        $driver = self::driver();
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
            throw new DriverUnavailable($driver);
        }

        $dsn = self::dsn($driver);
        $user = self::env('DB_USER');
        $password = self::env('DB_PASSWORD');

        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private static function dsn(string $driver): string
    {
        $dsn = self::env('DB_DSN');
        if ($dsn !== null) {
            return $dsn;
        }

        return match ($driver) {
            'sqlite' => 'sqlite::memory:',
            'mysql' => 'mysql:host=127.0.0.1;port=33306;dbname=relational_test',
            'pgsql' => 'pgsql:host=127.0.0.1;port=55432;dbname=relational_test',
            default => throw new InvalidArgumentException('Unsupported driver: ' . $driver),
        };
    }

    private static function extractDsnScheme(string $dsn): string
    {
        $colon = strpos($dsn, ':');
        if ($colon === false || $colon === 0) {
            throw new InvalidDriverConfiguration(
                'DB_DSN must start with a driver scheme like "mysql:", got: ' . $dsn,
            );
        }

        return substr($dsn, 0, $colon);
    }

    private static function env(string $name): string|null
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }
}
