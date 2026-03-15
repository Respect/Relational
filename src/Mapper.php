<?php

declare(strict_types=1);

namespace Respect\Relational;

use PDO;
use PDOException;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Respect\Data\AbstractMapper;
use Respect\Data\CollectionIterator;
use Respect\Data\Collections as c;
use Respect\Data\Collections\Collection;
use SplObjectStorage;
use Throwable;

use function array_combine;
use function array_diff;
use function array_fill;
use function array_intersect_key;
use function array_keys;
use function array_merge;
use function array_pop;
use function array_reverse;
use function class_exists;
use function count;
use function get_object_vars;
use function is_array;
use function is_numeric;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function preg_match;
use function preg_replace;
use function str_replace;

/** Maps objects to database operations */
final class Mapper extends AbstractMapper implements
    c\Filterable,
    c\Mixable,
    c\Typable
{
    protected readonly Db $db;

    public string $entityNamespace = '\\';

    public bool $disableEntityConstructor = false;

    public function __construct(PDO|Db $db)
    {
        parent::__construct();

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
        $next = $onCollection->getNext();

        if ($this->filterable($onCollection)) {
            $next->setMapper($this);
            $next->persist($object);

            return true;
        }

        if ($next) {
            $remote = $this->getStyle()->remoteIdentifier($next->getName());
            $next->setMapper($this);
            $next->persist($this->inferGet($object, $remote));
        }

        foreach ($onCollection->getChildren() as $child) {
            $remote = $this->getStyle()->remoteIdentifier($child->getName());
            $child->persist($this->inferGet($object, $remote));
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

    public function getFilters(Collection $collection): mixed
    {
        return $collection->getExtra('filters');
    }

    public function getMixins(Collection $collection): mixed
    {
        return $collection->getExtra('mixins');
    }

    public function getType(Collection $collection): mixed
    {
        return $collection->getExtra('type');
    }

    public function mixable(Collection $collection): bool
    {
        return $collection->have('mixins');
    }

    public function typable(Collection $collection): bool
    {
        return $collection->have('type');
    }

    public function filterable(Collection $collection): bool
    {
        return $collection->have('filters');
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
    protected function extractAndOperateMixins(Collection $collection, array $cols): array
    {
        if (!$this->mixable($collection)) {
            return $cols;
        }

        foreach ($this->getMixins($collection) as $mix => $spec) {
            //Extract from $cols only the columns from the mixin
            $mixCols = array_intersect_key(
                $cols,
                array_combine(
                    //create array with keys only
                    $spec,
                    array_fill(0, count($spec), ''),
                ),
            );
            if (isset($cols[$mix . '_id'])) {
                $mixCols['id'] = $cols[$mix . '_id'];
                $cols = array_diff($cols, $mixCols); //Remove mixin columns
                $this->rawUpdate($mixCols, $this->__get($mix));
            } else {
                $mixCols['id'] = null;
                $cols = array_diff($cols, $mixCols); //Remove mixin columns
                $this->rawInsert($mixCols, $this->__get($mix));
            }
        }

        return $cols;
    }

    /**
     * @param array<string, mixed> $columns
     *
     * @return array<string, mixed>
     */
    protected function guessCondition(array &$columns, Collection $collection): array
    {
        $primaryName    = $this->getStyle()->identifier($collection->getName());
        $condition      = [$primaryName => $columns[$primaryName]];
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
        $columns   = $this->extractAndOperateMixins($collection, $columns);
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
        $columns    = $this->extractAndOperateMixins($collection, $columns);
        $name       = $collection->getName();
        $isInserted = $this->db
            ->insertInto($name, $columns)
            ->values($columns)
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
        $this->inferSet($entity, $primaryName, $identity);

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
        $cols = $this->getAllProperties($entity);

        foreach ($cols as &$c) {
            if (!is_object($c)) {
                continue;
            }

            $c = $this->inferGet($c, $primaryName);
        }

        return $cols;
    }

    /** @param array<string, Collection> $collections */
    protected function buildSelectStatement(Sql $sql, array $collections): Sql
    {
        $selectTable = [];
        foreach ($collections as $tableSpecifier => $c) {
            if ($this->mixable($c)) {
                foreach ($this->getMixins($c) as $mixin => $columns) {
                    foreach ($columns as $col) {
                        $selectTable[] = $tableSpecifier . '_mix' . $mixin . '.' . $col;
                    }

                    $selectTable[] = $tableSpecifier . '_mix' . $mixin . '.' .
                    $this->getStyle()->identifier($mixin) .
                    ' as ' . $mixin . '_id';
                }
            }

            if ($this->filterable($c)) {
                $filters = $this->getFilters($c);
                if ($filters) {
                    $pkName = $tableSpecifier . '.' .
                        $this->getStyle()->identifier($c->getName());

                    if ($filters == ['*']) {
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

                    if ($c->getNext()) {
                        $selectColumns[] = $tableSpecifier . '.' .
                            $this->getStyle()->remoteIdentifier(
                                $c->getNext()->getName(),
                            );
                    }

                    $selectTable = array_merge($selectTable, $selectColumns);
                }
            } else {
                $selectTable[] = $tableSpecifier . '.*';
            }
        }

        return $sql->select($selectTable);
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

        return $sql->where($conditions);
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
        $aliasedPk          = $this->getStyle()->identifier($entity);
        $aliasedPk          = $alias . '.' . $aliasedPk;

        if (is_scalar($originalConditions)) {
            $parsedConditions = [$aliasedPk => $originalConditions];
        } elseif (is_array($originalConditions)) {
            foreach ($originalConditions as $column => $value) {
                if (is_numeric($column)) {
                    $parsedConditions[$column] = preg_replace(
                        '/' . $entity . '[.](\w+)/',
                        $alias . '.$1',
                        $value,
                    );
                } else {
                    $parsedConditions[$alias . '.' . $column] = $value;
                }
            }
        }

        return $parsedConditions;
    }

    protected function parseMixins(Sql $sql, Collection $collection, string $entity): void
    {
        if (!$this->mixable($collection)) {
            return;
        }

        foreach (array_keys($this->getMixins($collection)) as $mix) {
            $sql->innerJoin($mix);
            $sql->as($entity . '_mix' . $mix);
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
        $parent = $collection->getParentName();
        $next   = $collection->getNextName();

        $parentAlias = $parent ? $aliases[$parent] : null;
        $aliases[$entity] = $alias;
        $conditions = $this->parseConditions(
            $conditions,
            $collection,
            $alias,
        ) ?: $conditions;

        //No parent collection means it's the first table in the query
        if ($parentAlias === null) {
            $sql->from($entity);
            $this->parseMixins($sql, $collection, $entity);

            return null;
        }

        if ($collection->isRequired()) {
            $sql->innerJoin($entity);
        } else {
            $sql->leftJoin($entity);
        }

        $this->parseMixins($sql, $collection, $entity);

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

    protected function getNewEntityByName(string $entityName): object
    {
        $entityName = $this->getStyle()->styledName($entityName);
        $entityClass = $this->entityNamespace . $entityName;
        $entityClass = class_exists($entityClass) ? $entityClass : '\stdClass';
        $entityReflection = new ReflectionClass($entityClass);

        if (!$this->disableEntityConstructor) {
            return $entityReflection->newInstanceArgs();
        }

        return $entityReflection->newInstanceWithoutConstructor();
    }

    protected function transformSingleRow(object $row, string $entityName): object
    {
        $newRow = $this->getNewEntityByName($entityName);

        foreach (get_object_vars($row) as $prop => $value) {
            $this->inferSet($newRow, $prop, $value);
        }

        return $newRow;
    }

    protected function inferSet(object &$entity, string $prop, mixed $value): void
    {
        if ($entity === $value) {
            return;
        }

        try {
            $mirror = new ReflectionProperty($entity, $prop);
            $mirror->setValue($entity, $value);
        } catch (ReflectionException) {
            $entity->{$prop} = $value;
        }
    }

    protected function inferGet(object &$object, string $prop): mixed
    {
        try {
            $mirror = new ReflectionProperty($object, $prop);

            return $mirror->getValue($object);
        } catch (ReflectionException) {
            return null;
        }
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
            $columnMeta    = $statement->getColumnMeta($col);
            if ($columnMeta === false) {
                continue;
            }

            $columnName    = $columnMeta['name'];
            $primaryName   = $this->getStyle()->identifier(
                $entities[$entityInstance]->getName(),
            );

            $this->inferSet($entityInstance, $columnName, $value);

            if ($primaryName != $columnName) {
                continue;
            }

            $entityInstance = array_pop($entitiesInstances);
        }

        return $entities;
    }

    /**
     * @param SplObjectStorage<object, Collection> $entities
     *
     * @return array<int, object>
     */
    protected function buildEntitiesInstances(
        Collection $collection,
        SplObjectStorage $entities,
    ): array {
        $entitiesInstances = [];

        foreach (CollectionIterator::recursive($collection) as $c) {
            if ($this->filterable($c) && !$this->getFilters($c)) {
                continue;
            }

            $entityInstance = $this->getNewEntityByName($c->getName());
            $mixins = [];

            if ($this->mixable($c)) {
                $mixins = $this->getMixins($c);
                $mixinCount = count($mixins);
                for ($i = 0; $i < $mixinCount; $i++) {
                    $entitiesInstances[] = $entityInstance;
                }
            }

            $entities[$entityInstance] = $c;
            $entitiesInstances[] = $entityInstance;
        }

        return $entitiesInstances;
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    protected function postHydrate(SplObjectStorage $entities): void
    {
        $entitiesClone = clone $entities;

        foreach ($entities as $instance) {
            foreach ($this->getAllProperties($instance) as $field => $v) {
                if (!$this->getStyle()->isRemoteIdentifier($field)) {
                    continue;
                }

                foreach ($entitiesClone as $sub) {
                    $this->tryHydration($entities, $sub, $field, $v);
                }

                $this->inferSet($instance, $field, $v);
            }
        }
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    protected function tryHydration(SplObjectStorage $entities, object $sub, string $field, mixed &$v): void
    {
        $tableName = $entities[$sub]->getName();
        $primaryName = $this->getStyle()->identifier($tableName);

        if (
            $tableName !== $this->getStyle()->remoteFromIdentifier($field)
                || $this->inferGet($sub, $primaryName) !== $v
        ) {
            return;
        }

        $v = $sub;
    }

    protected function getSetterStyle(string $name): string
    {
        $name = str_replace('_', '', $this->getStyle()->styledProperty($name));

        return 'set' . $name;
    }

    /** @return array<string, mixed> */
    protected function getAllProperties(object $object): array
    {
        $cols = get_object_vars($object);
        $ref = new ReflectionClass($object);
        foreach ($ref->getProperties() as $prop) {
            $docComment = $prop->getDocComment();
            if ($docComment !== false && preg_match('/@Relational\\\isNotColumn/', $docComment)) {
                continue;
            }

            $cols[$prop->name] = $prop->getValue($object);
        }

        return $cols;
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
            $query->appendQuery($withExtra);
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
        $name        = $collection->getName();
        $entityName  = $name;
        $row         = $statement->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            return false;
        }

        if ($this->typable($collection)) {
            $entityName = $this->inferGet($row, $this->getType($collection));
        }

        $entities = new SplObjectStorage();
        $entities[$this->transformSingleRow($row, $entityName)] = $collection;

        return $entities;
    }

    /** @return SplObjectStorage<object, Collection>|false */
    private function fetchMulti(
        Collection $collection,
        PDOStatement $statement,
    ): SplObjectStorage|false {
        $entities       = [];
        $row            = $statement->fetch(PDO::FETCH_NUM);

        if (!$row) {
            return false;
        }

        $this->postHydrate(
            $entities = $this->createEntities($row, $statement, $collection),
        );

        return $entities;
    }
}
