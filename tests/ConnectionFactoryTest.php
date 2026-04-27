<?php

declare(strict_types=1);

namespace Respect\Relational;

use PHPUnit\Framework\TestCase;
use Respect\Relational\Database\ConnectionFactory;
use Respect\Relational\Database\InvalidDriverConfiguration;

use function getenv;
use function putenv;

class ConnectionFactoryTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (['DB_DRIVER', 'DB_DSN', 'DB_USER', 'DB_PASSWORD'] as $name) {
            $this->envBackup[$name] = getenv($name);
            putenv($name);
        }
    }

    public function testDefaultsToSqliteWhenEnvUnset(): void
    {
        $this->assertSame('sqlite', ConnectionFactory::driver());
    }

    public function testHonorsDbDriverEnvVar(): void
    {
        putenv('DB_DRIVER=mysql');
        $this->assertSame('mysql', ConnectionFactory::driver());
    }

    public function testDsnSchemeIsAuthoritativeWhenDbDriverUnset(): void
    {
        putenv('DB_DSN=pgsql:host=foo;dbname=bar');
        $this->assertSame('pgsql', ConnectionFactory::driver());
    }

    public function testAcceptsMatchingDbDriverAndDsnScheme(): void
    {
        putenv('DB_DRIVER=mysql');
        putenv('DB_DSN=mysql:host=foo;dbname=bar');
        $this->assertSame('mysql', ConnectionFactory::driver());
    }

    public function testThrowsWhenDbDriverAndDsnSchemeDisagree(): void
    {
        putenv('DB_DRIVER=mysql');
        putenv('DB_DSN=pgsql:host=foo;dbname=bar');
        $this->expectException(InvalidDriverConfiguration::class);
        $this->expectExceptionMessage('DB_DRIVER (mysql) does not match the scheme of DB_DSN (pgsql)');
        ConnectionFactory::driver();
    }

    public function testThrowsWhenDsnHasNoScheme(): void
    {
        putenv('DB_DSN=no-colon-here');
        $this->expectException(InvalidDriverConfiguration::class);
        ConnectionFactory::driver();
    }

    public function testThrowsWhenDsnStartsWithColon(): void
    {
        putenv('DB_DSN=:nothing-before-colon');
        $this->expectException(InvalidDriverConfiguration::class);
        ConnectionFactory::driver();
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }
}
