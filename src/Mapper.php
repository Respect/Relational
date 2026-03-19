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
use SplObjectStorage;
use Throwable;

use function array_keys;
use function array_merge;
use function array_pop;
use function array_push;
use function array_reverse;
use function array_values;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;

/** Maps objects to database operations */
final class Mapper extends AbstractMapper
{
    protected readonly Db $db;

    public function __construct(PDO|Db $db, EntityFactory $entityFactory = new EntityFactory())
    {
        parent::__construct($entityFactory);

        $this->db = $db instanceof PDO ? new Db($db) : $db;
    }

    public function getDb(): Db
    {
        return $this->db;
    }

    public function fetch(Collection $collection, mixed $extra = null): mixed
    {
        $statement = $this->createStatement($collection, $extra);
        $hydrated = $this->fetchHydrated($collection, $statement);
        if (!$hydrated) {
            return false;
        }

        return $this->parseHydrated($hydrated);
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

        $next = $onCollection->getNext();

        if ($next) {
            $remote = $this->getStyle()->remoteIdentifier($next->getName());
            $next->setMapper($this);
            $next->persist($this->entityFactory->get($object, $remote));
        }

        foreach ($onCollection->getChildren() as $child) {
            $remote = $this->getStyle()->remoteIdentifier($child->getName());
            $child->persist($this->entityFactory->get($object, $remote));
        }

        return parent::persist($object, $onCollection);
    }

    public function flush(): void
    {
        $conn = $this->db->getConnection();
        $conn->beginTransaction();

        try {
            foreach ($this->changed as $entity) {
                $this->flushSingle($entity);
            }
        } catch (Throwable $e) {
            $conn->rollback();

            throw $e;
        }

        $this->reset();
        $conn->commit();
    }

    protected function flushSingle(object $entity): void
    {
        $coll    = $this->tracked[$entity];
        $cols    = $this->extractColumns($entity, $coll);

        if ($this->removed->offsetExists($entity)) {
            $this->rawDelete($cols, $coll, $entity);
        } elseif ($this->new->offsetExists($entity)) {
            $this->rawInsert($cols, $coll, $entity);
        } else {
            $this->rawUpdate($cols, $coll);
        }
    }

    /**
     * @param array<string, mixed> $cols
     *
     * @return array<string, mixed>
     */
    protected function extractAndOperateCompositions(Collection $collection, array $cols): array
    {
        if (!$collection instanceof Composite) {
            return $cols;
        }

        foreach ($collection->getCompositions() as $comp => $spec) {
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
    protected function guessCondition(array &$columns, Collection $collection): array
    {
        $primaryName = $this->getStyle()->identifier($collection->getName());
        $condition   = [[$primaryName, '=', $columns[$primaryName]]];
        unset($columns[$primaryName]);

        return $condition;
    }

    /** @param array<string, mixed> $condition */
    protected function rawDelete(
        array $condition,
        Collection $collection,
        object $entity,
    ): bool {
        $name      = $collection->getName();
        $columns   = $this->extractColumns($entity, $collection);
        $condition = $this->guessCondition($columns, $collection);

        return $this->db
            ->deleteFrom($name)
            ->where($condition)
            ->exec();
    }

    /** @param array<string, mixed> $columns */
    protected function rawUpdate(array $columns, Collection $collection): bool
    {
        $columns   = $this->extractAndOperateCompositions($collection, $columns);
        $name      = $collection->getName();
        $condition = $this->guessCondition($columns, $collection);

        return $this->db
            ->update($name)
            ->set($columns)
            ->where($condition)
            ->exec();
    }

    /** @param array<string, mixed> $columns */
    protected function rawInsert(
        array $columns,
        Collection $collection,
        object|null $entity = null,
    ): bool {
        $columns    = $this->extractAndOperateCompositions($collection, $columns);
        $name       = $collection->getName();
        $isInserted = $this->db
            ->insertInto($name, array_keys($columns))
            ->values(array_values($columns))
            ->exec();

        if ($entity !== null) {
            $this->checkNewIdentity($entity, $collection);
        }

        return $isInserted;
    }

    protected function checkNewIdentity(object $entity, Collection $collection): bool
    {
        $identity = null;
        try {
            $identity = $this->db->getConnection()->lastInsertId();
        } catch (PDOException) {
            //some drivers may throw an exception here, it is just irrelevant
            return false;
        }

        if (!$identity) {
            return false;
        }

        $primaryName = $this->getStyle()->identifier($collection->getName());
        $this->entityFactory->set($entity, $primaryName, $identity);

        return true;
    }

    protected function generateQuery(Collection $collection): Sql
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
    protected function extractColumns(object $entity, Collection $collection): array
    {
        $primaryName = $this->getStyle()->identifier($collection->getName());
        $cols = $this->entityFactory->extractProperties($entity);

        foreach ($cols as &$c) {
            if (!is_object($c)) {
                continue;
            }

            $c = $this->entityFactory->get($c, $primaryName);
        }

        return $cols;
    }

    /** @param array<string, Collection> $collections */
    protected function buildSelectStatement(Sql $sql, array $collections): Sql
    {
        $selectTable = [];
        foreach ($collections as $tableSpecifier => $c) {
            if ($c instanceof Composite) {
                foreach ($c->getCompositions() as $composition => $columns) {
                    foreach ($columns as $col) {
                        $selectTable[] = $tableSpecifier . '_comp' . $composition . '.' . $col;
                    }

                    $selectTable[] = $tableSpecifier . '_comp' . $composition . '.' .
                    $this->getStyle()->identifier($composition) .
                    ' as ' . $composition . '_id';
                }
            }

            if ($c instanceof Filtered) {
                $filters = $c->getFilters();
                if ($filters) {
                    $pkName = $tableSpecifier . '.' .
                        $this->getStyle()->identifier($c->getName());

                    if ($c->isIdentifierOnly()) {
                        $selectColumns = [$pkName];
                    } else {
                        $selectColumns = [
                            $tableSpecifier . '.' .
                            $this->getStyle()->identifier($c->getName()),
                        ];
                        foreach ($filters as $f) {
                            $selectColumns[] = $tableSpecifier . '.' . $f;
                        }
                    }

                    $nextName = $c->getNext()?->getName();
                    if ($nextName !== null) {
                        $selectColumns[] = $tableSpecifier . '.' .
                            $this->getStyle()->remoteIdentifier($nextName);
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
    protected function buildTables(Sql $sql, array $collections): Sql
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
    protected function parseConditions(array &$conditions, Collection $collection, string $alias): array
    {
        $entity             = $collection->getName();
        $originalConditions = $collection->getCondition();
        $parsedConditions   = [];
        $aliasedPk          = $alias . '.' . $this->getStyle()->identifier($entity);

        if (is_scalar($originalConditions)) {
            $parsedConditions[] = [$aliasedPk, '=', $originalConditions];
        } elseif (is_array($originalConditions)) {
            foreach ($originalConditions as $column => $value) {
                if (!empty($parsedConditions)) {
                    $parsedConditions[] = 'AND';
                }

                $parsedConditions[] = [$alias . '.' . $column, '=', $value];
            }
        }

        return $parsedConditions;
    }

    protected function parseCompositions(Sql $sql, Collection $collection, string $entity): void
    {
        if (!$collection instanceof Composite) {
            return;
        }

        foreach (array_keys($collection->getCompositions()) as $comp) {
            $sql->innerJoin($comp);
            $sql->as($entity . '_comp' . $comp);
        }
    }

    /**
     * @param array<string, string> $aliases
     * @param array<mixed> $conditions
     */
    protected function parseCollection(
        Sql $sql,
        Collection $collection,
        string $alias,
        array &$aliases,
        array &$conditions,
    ): mixed {
        $s      = $this->getStyle();
        $entity = $collection->getName();
        $parent = $collection->getParent()?->getName();
        $next   = $collection->getNext()?->getName();

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

            return null;
        }

        if ($collection->isRequired()) {
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

        return $sql->on([$onName => $onAlias]);
    }

    protected function hasComposition(string $entity, string|null $next, string|null $parent): bool
    {
        if ($next === null || $parent === null) {
            return false;
        }

        $s = $this->getStyle();

        return $entity === $s->composed($parent, $next)
            || $entity === $s->composed($next, $parent);
    }

    /**
     * @param array<int, mixed> $row
     *
     * @return SplObjectStorage<object, Collection>
     */
    protected function createEntities(
        array $row,
        PDOStatement $statement,
        Collection $collection,
    ): SplObjectStorage {
        $entities          = new SplObjectStorage();
        $entitiesInstances = $this->buildEntitiesInstances(
            $collection,
            $entities,
        );
        $entityInstance    = array_pop($entitiesInstances);

        //Reversely traverses the columns to avoid conflicting foreign key names
        foreach (array_reverse($row, true) as $col => $value) {
            /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
            $columnName    = $statement->getColumnMeta($col)['name'];
            $primaryName   = $this->getStyle()->identifier(
                $entities[$entityInstance]->getName(),
            );

            $this->entityFactory->set($entityInstance, $columnName, $value);

            if ($primaryName != $columnName) {
                continue;
            }

            $entityInstance = array_pop($entitiesInstances);
        }

        return $entities;
    }

    /** @param SplObjectStorage<object, Collection> $hydrated */
    private function parseHydrated(SplObjectStorage $hydrated): mixed
    {
        $this->tracked->addAll($hydrated);
        $hydrated->rewind();

        return $hydrated->current();
    }

    /** @return SplObjectStorage<object, Collection>|false */
    private function fetchHydrated(Collection $collection, PDOStatement $statement): SplObjectStorage|false
    {
        if (!$collection->hasMore()) {
            return $this->fetchSingle($collection, $statement);
        }

        return $this->fetchMulti($collection, $statement);
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
        $statement->execute($query->getParams());

        return $statement;
    }

    /** @return SplObjectStorage<object, Collection>|false */
    private function fetchSingle(
        Collection $collection,
        PDOStatement $statement,
    ): SplObjectStorage|false {
        $row = $statement->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            return false;
        }

        $entityName = $collection->resolveEntityName($this->entityFactory, $row);
        $entities = new SplObjectStorage();
        $entities[$this->entityFactory->hydrate($row, $entityName)] = $collection;

        return $entities;
    }

    /** @return SplObjectStorage<object, Collection>|false */
    private function fetchMulti(
        Collection $collection,
        PDOStatement $statement,
    ): SplObjectStorage|false {
        $row = $statement->fetch(PDO::FETCH_NUM);

        if (!$row) {
            return false;
        }

        $entities = $this->createEntities($row, $statement, $collection);
        $this->postHydrate($entities);

        return $entities;
    }
}
