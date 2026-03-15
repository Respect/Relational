<?php

declare(strict_types=1);

namespace Respect\Relational;

use PDO;
use PDOStatement;

final class Db
{
    protected PDO $connection;
    protected Sql $currentSql;
    protected Sql $protoSql;

    public function __call(string $methodName, array $arguments): static
    {
        $this->currentSql->__call($methodName, $arguments);

        return $this;
    }

    public function __construct(PDO $connection, Sql|null $sqlPrototype = null)
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection = $connection;
        $this->protoSql = $sqlPrototype ?: new Sql();
        $this->currentSql = clone $this->protoSql;
    }

    public function exec(): bool
    {
        return (bool) $this->executeStatement();
    }

    public function fetch(mixed $object = '\stdClass', mixed $extra = null): mixed
    {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);

        return is_callable($object) ? $object($result) : $result;
    }

    public function fetchAll(mixed $object = '\stdClass', mixed $extra = null): mixed
    {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);

        return is_callable($object) ? array_map($object, $result) : $result;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function getSql(): Sql
    {
        return $this->currentSql;
    }

    public function prepare(string $queryString, mixed $object = '\stdClass', array|null $extra = null): PDOStatement
    {
        $statement = $this->connection->prepare($queryString);

        match (true) {
            is_int($object) => $statement->setFetchMode($object),
            '\stdClass' === $object || 'stdClass' === $object => $statement->setFetchMode(PDO::FETCH_OBJ),
            is_callable($object) => $statement->setFetchMode(PDO::FETCH_OBJ),
            is_object($object) => $statement->setFetchMode(PDO::FETCH_INTO, $object),
            is_array($object) => $statement->setFetchMode(PDO::FETCH_ASSOC),
            is_null($extra) => $statement->setFetchMode(PDO::FETCH_CLASS, $object),
            default => $statement->setFetchMode(PDO::FETCH_CLASS, $object, $extra),
        };

        return $statement;
    }

    public function query(string $rawSql, array|null $params = null): static
    {
        $this->currentSql->setQuery($rawSql, $params);

        return $this;
    }

    protected function executeStatement(mixed $object = '\stdClass', mixed $extra = null): PDOStatement
    {
        $statement = $this->prepare((string) $this->currentSql, $object, $extra);
        $statement->execute($this->currentSql->getParams());
        $this->currentSql = clone $this->protoSql;

        return $statement;
    }

    protected function performFetch(string $method, mixed $object = '\stdClass', mixed $extra = null): mixed
    {
        $statement = $this->executeStatement($object, $extra);
        $result = $statement->{$method}();

        return $result;
    }
}
