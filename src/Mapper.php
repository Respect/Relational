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
use Respect\Data\Hydrator;
use Respect\Data\Hydrators\PrestyledAssoc;
use SplObjectStorage;
use Throwable;

use function array_keys;
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

    /** @var SplObjectStorage<object, true> */
    private SplObjectStorage $persisting;

    public function __construct(PDO|Db $db, Hydrator $hydrator = new PrestyledAssoc())
    {
        parent::__construct($hydrator);

        $this->db = $db instanceof PDO ? new Db($db) : $db;
        $this->persisting = new SplObjectStorage();
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

    public function persist(object $object, Collection $onCollection): object
    {
        if ($onCollection instanceof Filtered) {
            return parent::persist($object, $onCollection);
        }

        if ($this->persisting->offsetExists($object)) {
            return parent::persist($object, $onCollection);
        }

        $this->persisting[$object] = true;

        try {
            $connectsTo = $onCollection->connectsTo;

            if ($connectsTo) {
                $remote = $this->style->remoteIdentifier($connectsTo->name);
                $related = $this->getRelatedEntity($object, $remote);
                if ($related !== null) {
                    $this->persist($related, $connectsTo);
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
        } finally {
            $this->persisting->offsetUnset($object);
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
            $this->reset();

            throw $e;
        }

        $this->reset();
        $conn->commit();
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
     * Extract composed columns from parent, UPDATE child tables using FK relationship.
     *
     * For existing entities (UPDATE path), the parent PK is known and we can
     * update the child table directly: UPDATE comment SET text=? WHERE post_id=?
     *
     * For new entities (INSERT path), child inserts happen after the parent
     * via insertCompositionChildren().
     *
     * @param array<string, mixed> $cols
     *
     * @return array<string, mixed>
     */
    private function extractAndOperateCompositions(Collection $collection, array $cols): array
    {
        if (!$collection instanceof Composite) {
            return $cols;
        }

        $parentPk = $this->style->identifier($collection->name);
        $parentPkValue = $cols[$parentPk] ?? null;
        $fkToParent = $this->style->remoteIdentifier($collection->name);

        foreach ($collection->compositions as $comp => $spec) {
            $compCols = [];
            foreach ($spec as $key) {
                $dbKey = $this->style->realProperty($key);
                if (!isset($cols[$dbKey])) {
                    continue;
                }

                $compCols[$dbKey] = $cols[$dbKey];
                unset($cols[$dbKey]);
            }

            if ($parentPkValue === null || empty($compCols)) {
                continue;
            }

            $this->db
                ->update($comp)
                ->set($compCols)
                ->where([[$fkToParent, '=', $parentPkValue]])
                ->exec();
        }

        return $cols;
    }

    private function insertCompositionChildren(Collection $collection, object|null $entity): void
    {
        if (!$collection instanceof Composite || $entity === null) {
            return;
        }

        $parentPk = $this->style->identifier($collection->name);
        $parentPkValue = $this->entityFactory->get($entity, $parentPk);

        if ($parentPkValue === null) {
            return;
        }

        $fkToParent = $this->style->remoteIdentifier($collection->name);
        $entityCols = $this->entityFactory->extractColumns($entity);

        foreach ($collection->compositions as $comp => $spec) {
            $compCols = [];
            foreach ($spec as $key) {
                $dbKey = $this->style->realProperty($key);
                if (!isset($entityCols[$key])) {
                    continue;
                }

                $compCols[$dbKey] = $entityCols[$key];
            }

            if (empty($compCols)) {
                continue;
            }

            $compCols[$fkToParent] = $parentPkValue;
            $this->db
                ->insertInto($comp, array_keys($compCols))
                ->values(array_values($compCols))
                ->exec();
        }
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

        $this->insertCompositionChildren($collection, $entity);

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

        $this->entityFactory->set($entity, $this->style->identifier($collection->name), (int) $identity);

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
        $cols = $this->filterColumns(
            $this->entityFactory->extractColumns($entity),
            $collection,
        );

        $dbCols = [];
        foreach ($cols as $key => $value) {
            $dbCols[$this->style->realProperty($key)] = $value;
        }

        return $dbCols;
    }

    /** @param array<string, Collection> $collections */
    private function buildSelectStatement(Sql $sql, array $collections): Sql
    {
        $selectTable = [];
        foreach ($collections as $tableSpecifier => $c) {
            if ($c instanceof Filtered) {
                $filters = $c->filters;
                if ($filters) {
                    $fields = $this->entityFactory->enumerateFields($c->name);
                    $pk = $this->style->identifier($c->name);
                    $selectTable[] = self::aliasedColumn($tableSpecifier, $pk, $fields[$pk] ?? $pk);

                    if (!$c->identifierOnly) {
                        foreach ($filters as $f) {
                            $selectTable[] = self::aliasedColumn($tableSpecifier, $f, $fields[$f] ?? $f);
                        }
                    }

                    $connectedName = $c->connectsTo?->name;
                    if ($connectedName !== null) {
                        $fk = $this->style->remoteIdentifier($connectedName);
                        $selectTable[] = self::aliasedColumn($tableSpecifier, $fk, $fields[$fk] ?? $fk);
                    }
                }
            } else {
                foreach ($this->entityFactory->enumerateFields($c->name) as $dbCol => $styledProp) {
                    $selectTable[] = self::aliasedColumn($tableSpecifier, $dbCol, $styledProp);
                }
            }

            // Composition columns come after entity columns so they override on collision
            if (!$c instanceof Composite) {
                continue;
            }

            foreach ($c->compositions as $composition => $columns) {
                $compPrefix = $tableSpecifier . Composite::COMPOSITION_MARKER . $composition;
                foreach ($columns as $col) {
                    $selectTable[] = self::aliasedColumn($compPrefix, $col, $col);
                }
            }
        }

        return $sql->select(...$selectTable);
    }

    /** @return array<string, string> Alias array for Sql::select() */
    private static function aliasedColumn(string $specifier, string $dbCol, string $prop): array
    {
        return [$specifier . '__' . $prop => $specifier . '.' . $dbCol];
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
            $alias = $entity . Composite::COMPOSITION_MARKER . $comp;
            $sql->innerJoin($comp);
            $sql->as($alias);
            $sql->on([
                $alias . '.' . $this->style->remoteIdentifier($entity)
                    => $entity . '.' . $this->style->identifier($entity),
            ]);
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
        $connected = $collection->connectsTo?->name;

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

        if ($this->hasComposition($entity, $connected, $parent)) {
            $onName  = $alias . '.' . $s->remoteIdentifier($parent);
            $onAlias = $aliasedParentPk;
        } else {
            $onName  = $parentAlias . '.' . $s->remoteIdentifier($entity);
            $onAlias = $aliasedPk;
        }

        $sql->on([$onName => $onAlias]);
    }

    private function hasComposition(string $entity, string|null $connected, string|null $parent): bool
    {
        if ($connected === null || $parent === null) {
            return false;
        }

        return $entity === $this->style->composed($parent, $connected)
            || $entity === $this->style->composed($connected, $parent);
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
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $this->hydrator->hydrateAll($row, $collection);
    }

    private function createStatement(
        Collection $collection,
        mixed $withExtra = null,
    ): PDOStatement {
        $query = $this->generateQuery($collection);

        if ($withExtra instanceof Sql) {
            $query->concat($withExtra);
        }

        $statement = $this->db->prepare((string) $query, PDO::FETCH_ASSOC);
        $statement->execute($query->params);

        return $statement;
    }
}
