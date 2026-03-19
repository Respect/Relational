<?php

declare(strict_types=1);

namespace Respect\Relational;

use PDO;
use PDOStatement;
use stdClass;

use function array_map;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;

final class Db
{
    private Sql $currentSql;

    private readonly Sql $protoSql;

    public function __construct(public readonly PDO $connection, Sql|null $sqlPrototype = null)
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->protoSql = $sqlPrototype ?: new Sql();
        $this->currentSql = clone $this->protoSql;
    }

    public function exec(): bool
    {
        return (bool) $this->executeStatement();
    }

    /**
     * @param int|string|array<mixed>|callable|object $object
     * @param array<mixed>|null $extra
     */
    public function fetch(int|string|array|callable|object $object = stdClass::class, array|null $extra = null): mixed
    {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);

        return is_callable($object) ? $object($result) : $result;
    }

    /**
     * @param int|string|array<mixed>|callable|object $object
     * @param array<mixed>|null $extra
     */
    public function fetchAll(
        int|string|array|callable|object $object = stdClass::class,
        array|null $extra = null,
    ): mixed {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);

        return is_callable($object) ? array_map($object, $result) : $result;
    }

    public function getSql(): Sql
    {
        return $this->currentSql;
    }

    /**
     * @param int|string|array<mixed>|callable|object $object
     * @param array<int, mixed>|null $extra
     */
    public function prepare(
        string $queryString,
        int|string|array|callable|object $object = stdClass::class,
        array|null $extra = null,
    ): PDOStatement {
        $statement = $this->connection->prepare($queryString);

        match (true) {
            is_int($object) => $statement->setFetchMode($object),
            $object === stdClass::class => $statement->setFetchMode(PDO::FETCH_OBJ),
            is_callable($object) => $statement->setFetchMode(PDO::FETCH_OBJ),
            is_object($object) => $statement->setFetchMode(PDO::FETCH_INTO, $object),
            is_array($object) => $statement->setFetchMode(PDO::FETCH_ASSOC),
            $extra === null => $statement->setFetchMode(PDO::FETCH_CLASS, $object),
            default => $statement->setFetchMode(PDO::FETCH_CLASS, $object, $extra),
        };

        return $statement;
    }

    /**
     * @param int|string|array<mixed>|callable|object $object
     * @param array<mixed>|null $extra
     */
    private function executeStatement(
        int|string|array|callable|object $object = stdClass::class,
        array|null $extra = null,
    ): PDOStatement {
        $statement = $this->prepare((string) $this->currentSql, $object, $extra);
        $statement->execute($this->currentSql->params);
        $this->currentSql = clone $this->protoSql;

        return $statement;
    }

    /**
     * @param int|string|array<mixed>|callable|object $object
     * @param array<mixed>|null $extra
     */
    private function performFetch(
        string $method,
        int|string|array|callable|object $object = stdClass::class,
        array|null $extra = null,
    ): mixed {
        return $this->executeStatement($object, $extra)->{$method}();
    }

    /** @param array<mixed> $arguments */
    public function __call(string $methodName, array $arguments): static
    {
        $this->currentSql->__call($methodName, $arguments);

        return $this;
    }
}
