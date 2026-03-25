<?php

declare(strict_types=1);

namespace Respect\Relational;

use PDO;
use PDOException;
use PDOStatement;
use Respect\Data\AbstractMapper;
use Respect\Data\CollectionIterator;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Filtered;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use Respect\Relational\Hydrators\FlatNum;
use SplObjectStorage;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_push;
use function array_values;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;

/** Maps objects to database operations */
final class Mapper extends AbstractMapper
{
    public readonly Db $db;

    private PDOStatement $lastStatement;

    public function __construct(PDO|Db $db, EntityFactory $entityFactory = new EntityFactory())
    {
        parent::__construct($entityFactory);

        $this->db = $db instanceof PDO ? new Db($db) : $db;
    }

    public function fetch(Collection $collection, mixed $extra = null): mixed
    {
        if ($extra === null) {
            $cached = $this->findInIdentityMap($collection);
            if ($cached !== null) {
                return $cached;
            }
        }

        $hydrated = $this->fetchHydrated($collection, $this->createStatement($collection, $extra));

        return $hydrated ? $this->parseHydrated($hydrated) : false;
    }

    /** @return array<int, mixed> */
    public function fetchAll(Collection $collection, mixed $extra = null): array
    {
        $statement = $this->createStatement($collection, $extra);

        $entities = [];
        while ($hydrated = $this->fetchHydrated($collection, $statement)) {
            $entities[] = $this->parseHydrated($hydrated);
        }

        return $entities;
    }

    public function persist(object $object, Collection $onCollection): bool
    {
        if ($onCollection instanceof Filtered) {
            return parent::persist($object, $onCollection);
        }

        $next = $onCollection->next;

        if ($next) {
            $remote = $this->style->remoteIdentifier($next->name);
            $related = $this->getRelatedEntity($object, $remote);
            if ($related !== null) {
                $this->persist($related, $next);
            }
        }

        foreach ($onCollection->children as $child) {
            $remote = $this->style->remoteIdentifier($child->name);
            $related = $this->getRelatedEntity($object, $remote);
            if ($related === null) {
                continue;
            }

            $this->persist($related, $child);
        }

        return parent::persist($object, $onCollection);
    }

    public function flush(): void
    {
        $conn = $this->db->connection;
        $conn->beginTransaction();

        try {
            foreach ($this->pending as $entity) {
                $this->flushSingle($entity);
            }
        } catch (Throwable $e) {
            $conn->rollback();

            throw $e;
        }

        $this->reset();
        $conn->commit();
    }

    protected function defaultHydrator(Collection $collection): Hydrator
    {
        return new FlatNum($this->lastStatement);
    }

    /** Resolve related entity from relation property or FK field */
    private function getRelatedEntity(object $object, string $remoteField): object|null
    {
        $relation = $this->style->relationProperty($remoteField);
        if ($relation !== null) {
            $value = $this->entityFactory->get($object, $relation);
            if (is_object($value)) {
                return $value;
            }
        }

        $value = $this->entityFactory->get($object, $remoteField);

        return is_object($value) ? $value : null;
    }

    private function flushSingle(object $entity): void
    {
        $coll = $this->tracked[$entity];
        $cols = $this->extractColumns($entity, $coll);
        $op   = $this->pending[$entity];

        match ($op) {
            'delete' => $this->rawDelete($cols, $coll, $entity),
            'insert' => $this->rawInsert($cols, $coll, $entity),
            default  => $this->rawUpdate($cols, $coll),
        };

        if ($op === 'delete') {
            $this->evictFromIdentityMap($entity, $coll);
        } else {
            $this->registerInIdentityMap($entity, $coll);
        }
    }

    /**
     * @param array<string, mixed> $cols
     *
     * @return array<string, mixed>
     */
    private function extractAndOperateCompositions(Collection $collection, array $cols): array
    {
        if (!$collection instanceof Composite) {
            return $cols;
        }

        foreach ($collection->compositions as $comp => $spec) {
            $compCols = [];
            foreach ($spec as $key) {
                if (!isset($cols[$key])) {
                    continue;
                }

                $compCols[$key] = $cols[$key];
                unset($cols[$key]);
            }

            if (isset($cols[$comp . '_id'])) {
                $compCols['id'] = $cols[$comp . '_id'];
                unset($cols[$comp . '_id']);
                $this->rawUpdate($compCols, $this->__get($comp));
            } else {
                $compCols['id'] = null;
                $this->rawInsert($compCols, $this->__get($comp));
            }
        }

        return $cols;
    }

    /**
     * @param array<string, mixed> $columns
     *
     * @return array<int, array<int, mixed>>
     */
    private function guessCondition(array &$columns, Collection $collection): array
    {
        $primaryName = $this->style->identifier($collection->name);
        $condition   = [[$primaryName, '=', $columns[$primaryName]]];
        unset($columns[$primaryName]);

        return $condition;
    }

    /** @param array<string, mixed> $condition */
    private function rawDelete(
        array $condition,
        Collection $collection,
        object $entity,
    ): bool {
        $columns   = $this->extractColumns($entity, $collection);
        $condition = $this->guessCondition($columns, $collection);

        return $this->db
            ->deleteFrom($collection->name)
            ->where($condition)
            ->exec();
    }

    /** @param array<string, mixed> $columns */
    private function rawUpdate(array $columns, Collection $collection): bool
    {
        $columns   = $this->extractAndOperateCompositions($collection, $columns);
        $condition = $this->guessCondition($columns, $collection);

        return $this->db
            ->update($collection->name)
            ->set($columns)
            ->where($condition)
            ->exec();
    }

    /** @param array<string, mixed> $columns */
    private function rawInsert(
        array $columns,
        Collection $collection,
        object|null $entity = null,
    ): bool {
        $columns = $this->extractAndOperateCompositions($collection, $columns);
        $result  = $this->db
            ->insertInto($collection->name, array_keys($columns))
            ->values(array_values($columns))
            ->exec();

        if ($entity !== null) {
            $this->checkNewIdentity($entity, $collection);
        }

        return $result;
    }

    private function checkNewIdentity(object $entity, Collection $collection): bool
    {
        try {
            $identity = $this->db->connection->lastInsertId();
        } catch (PDOException) {
            return false;
        }

        if (!$identity) {
            return false;
        }

        $this->entityFactory->set($entity, $this->style->identifier($collection->name), $identity);

        return true;
    }

    private function generateQuery(Collection $collection): Sql
    {
        $collections = iterator_to_array(
            CollectionIterator::recursive($collection),
            true,
        );
        $sql = new Sql();

        $this->buildSelectStatement($sql, $collections);
        $this->buildTables($sql, $collections);

        return $sql;
    }

    /** @return array<string, mixed> */
    private function extractColumns(object $entity, Collection $collection): array
    {
        $primaryName = $this->style->identifier($collection->name);
        $cols = $this->entityFactory->extractProperties($entity);

        foreach ($cols as $key => $c) {
            if (is_object($c) && $this->style->isRelationProperty($key)) {
                unset($cols[$key]);

                continue;
            }

            if (is_object($c)) {
                $cols[$key] = $this->entityFactory->get($c, $primaryName);

                continue;
            }

            if (
                !$this->style->isRelationProperty($key)
                || !array_key_exists($this->style->remoteIdentifier($key), $cols)
            ) {
                continue;
            }

            unset($cols[$key]);
        }

        return $this->filterColumns($cols, $collection);
    }

    /** @param array<string, Collection> $collections */
    private function buildSelectStatement(Sql $sql, array $collections): Sql
    {
        $selectTable = [];
        foreach ($collections as $tableSpecifier => $c) {
            if ($c instanceof Composite) {
                foreach ($c->compositions as $composition => $columns) {
                    foreach ($columns as $col) {
                        $selectTable[] = $tableSpecifier . '_comp' . $composition . '.' . $col;
                    }

                    $selectTable[] = $tableSpecifier . '_comp' . $composition . '.' .
                    $this->style->identifier($composition) .
                    ' as ' . $composition . '_id';
                }
            }

            if ($c instanceof Filtered) {
                $filters = $c->filters;
                if ($filters) {
                    $pkName = $tableSpecifier . '.' .
                        $this->style->identifier($c->name);

                    if ($c->identifierOnly) {
                        $selectColumns = [$pkName];
                    } else {
                        $selectColumns = [
                            $tableSpecifier . '.' .
                            $this->style->identifier($c->name),
                        ];
                        foreach ($filters as $f) {
                            $selectColumns[] = $tableSpecifier . '.' . $f;
                        }
                    }

                    $nextName = $c->next?->name;
                    if ($nextName !== null) {
                        $selectColumns[] = $tableSpecifier . '.' .
                            $this->style->remoteIdentifier($nextName);
                    }

                    $selectTable = array_merge($selectTable, $selectColumns);
                }
            } else {
                $selectTable[] = $tableSpecifier . '.*';
            }
        }

        return $sql->select(...$selectTable);
    }

    /** @param array<string, Collection> $collections */
    private function buildTables(Sql $sql, array $collections): Sql
    {
        $conditions = $aliases = [];

        foreach ($collections as $alias => $collection) {
            $this->parseCollection(
                $sql,
                $collection,
                $alias,
                $aliases,
                $conditions,
            );
        }

        return empty($conditions) ? $sql : $sql->where($conditions);
    }

    /**
     * @param array<mixed> $conditions
     *
     * @return array<mixed>
     */
    private function parseConditions(array &$conditions, Collection $collection, string $alias): array
    {
        $parsedConditions = [];
        $aliasedPk = $alias . '.' . $this->style->identifier($collection->name);

        if (is_scalar($collection->condition)) {
            $parsedConditions[] = [$aliasedPk, '=', $collection->condition];
        } elseif (is_array($collection->condition)) {
            foreach ($collection->condition as $column => $value) {
                if (!empty($parsedConditions)) {
                    $parsedConditions[] = 'AND';
                }

                $parsedConditions[] = [$alias . '.' . $column, '=', $value];
            }
        }

        return $parsedConditions;
    }

    private function parseCompositions(Sql $sql, Collection $collection, string $entity): void
    {
        if (!$collection instanceof Composite) {
            return;
        }

        foreach (array_keys($collection->compositions) as $comp) {
            $sql->innerJoin($comp);
            $sql->as($entity . '_comp' . $comp);
        }
    }

    /**
     * @param array<string, string> $aliases
     * @param array<mixed> $conditions
     */
    private function parseCollection(
        Sql $sql,
        Collection $collection,
        string $alias,
        array &$aliases,
        array &$conditions,
    ): void {
        $s      = $this->style;
        $entity = $collection->name;
        $parent = $collection->parent?->name;
        $next   = $collection->next?->name;

        $parentAlias = $parent ? $aliases[$parent] : null;
        $aliases[$entity] = $alias;
        $parsed = $this->parseConditions(
            $conditions,
            $collection,
            $alias,
        );
        if (!empty($parsed)) {
            if (!empty($conditions)) {
                $conditions[] = 'AND';
            }

            array_push($conditions, ...$parsed);
        }

        //No parent collection means it's the first table in the query
        if ($parentAlias === null) {
            $sql->from($entity);
            $this->parseCompositions($sql, $collection, $entity);

            return;
        }

        if ($collection->required) {
            $sql->innerJoin($entity);
        } else {
            $sql->leftJoin($entity);
        }

        $this->parseCompositions($sql, $collection, $entity);

        if ($alias !== $entity) {
            $sql->as($alias);
        }

        $aliasedPk       = $alias . '.' . $s->identifier($entity);
        $aliasedParentPk = $parentAlias . '.' . $s->identifier($parent);

        if ($this->hasComposition($entity, $next, $parent)) {
            $onName  = $alias . '.' . $s->remoteIdentifier($parent);
            $onAlias = $aliasedParentPk;
        } else {
            $onName  = $parentAlias . '.' . $s->remoteIdentifier($entity);
            $onAlias = $aliasedPk;
        }

        $sql->on([$onName => $onAlias]);
    }

    private function hasComposition(string $entity, string|null $next, string|null $parent): bool
    {
        if ($next === null || $parent === null) {
            return false;
        }

        return $entity === $this->style->composed($parent, $next)
            || $entity === $this->style->composed($next, $parent);
    }

    /** @param SplObjectStorage<object, Collection> $hydrated */
    private function parseHydrated(SplObjectStorage $hydrated): object
    {
        $this->tracked->addAll($hydrated);

        // Register all hydrated entities in the PK-indexed identity map
        foreach ($hydrated as $entity) {
            $this->registerInIdentityMap($entity, $hydrated[$entity]);
        }

        $hydrated->rewind();

        return $hydrated->current();
    }

    /** @return SplObjectStorage<object, Collection>|false */
    private function fetchHydrated(Collection $collection, PDOStatement $statement): SplObjectStorage|false
    {
        $this->lastStatement = $statement;
        $hydrator = $this->resolveHydrator($collection);
        $row = $statement->fetch(PDO::FETCH_NUM);

        return $hydrator->hydrate($row, $collection, $this->entityFactory);
    }

    private function createStatement(
        Collection $collection,
        mixed $withExtra = null,
    ): PDOStatement {
        $query = $this->generateQuery($collection);

        if ($withExtra instanceof Sql) {
            $query->concat($withExtra);
        }

        $statement = $this->db->prepare((string) $query, PDO::FETCH_NUM);
        $statement->execute($query->params);

        return $statement;
    }
}
