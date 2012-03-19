<?php

namespace Respect\Relational;

use Exception;
use PDO;
use SplObjectStorage;
use InvalidArgumentException;
use PDOStatement;
use PDOException;
use stdClass;
use Respect\Data\AbstractMapper;
use Respect\Data\Collection;
use Respect\Data\CollectionIterator;

class Mapper extends AbstractMapper
{

    protected $db;
    protected $new;
    protected $tracked;
    protected $changed;
    protected $removed;
    protected $style;
    public $entityNamespace = '\\';

    public function __construct($db)
    {
        if ($db instanceof PDO)
            $this->db = new Db($db);
        elseif ($db instanceof Db)
            $this->db = $db;
        else
            throw new InvalidArgumentException('$db must be either an instance of Respect\Relational\Db or a PDO instance.');

        $this->tracked  = new SplObjectStorage;
        $this->changed  = new SplObjectStorage;
        $this->removed  = new SplObjectStorage;
        $this->new      = new SplObjectStorage;
    }

    public function fetch(Collection $fromCollection, $withExtra = null)
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $hydrated = $this->fetchHydrated($fromCollection, $statement);
        if (!$hydrated)
            return false;

        return $this->parseHydrated($hydrated);
    }

    public function fetchAll(Collection $fromCollection, $withExtra = null)
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $entities = array();

        while ($hydrated = $this->fetchHydrated($fromCollection, $statement))
            $entities[] = $this->parseHydrated($hydrated);

        return $entities;
    }

    public function persist($object, Collection $onCollection)
    {
        $this->changed[$object] = true;

        if ($this->isTracked($object))
            return true;

        $this->new[$object] = true;
        $this->markTracked($object, $onCollection->getName());
        return true;
    }

    public function flush()
    {
        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            foreach ($this->changed as $entity)
                $this->flushSingle($entity);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $this->changed = new SplObjectStorage;
        $this->removed = new SplObjectStorage;
        $this->new = new SplObjectStorage;
        $conn->commit();
    }

    protected function flushSingle($entity)
    {
        $name = $this->tracked[$entity]['table_name'];
        $cols = $this->extractColumns($entity, $name);

        if ($this->removed->contains($entity))
            $this->rawDelete($cols, $name, $entity);
        elseif ($this->new->contains($entity))
            $this->rawInsert($cols, $name, $entity);
        else
            $this->rawUpdate($cols, $name);
    }

    public function remove($object, Collection $fromCollection)
    {
        $this->changed[$object] = true;
        $this->removed[$object] = true;

        if ($this->isTracked($object))
            return true;

        $this->markTracked($object, $fromCollection->getName());
        return true;
    }

    protected function guessCondition(&$columns, $name)
    {
        $primaryName    = $this->getStyle()->primaryFromTable($name);
        $condition      = array($primaryName => $columns[$primaryName]);
        unset($columns[$primaryName]);
        return $condition;
    }

    protected function rawDelete(array $condition, $name, $entity)
    {
        $columns = $this->extractColumns($entity, $name);
        $condition = $this->guessCondition($columns, $name);

        return $this->db
                        ->deleteFrom($name)
                        ->where($condition)
                        ->exec();
    }

    protected function rawUpdate(array $columns, $name)
    {
        $condition = $this->guessCondition($columns, $name);

        return $this->db
                        ->update($name)
                        ->set($columns)
                        ->where($condition)
                        ->exec();
    }

    protected function rawInsert(array $columns, $name, $entity = null)
    {
        $isInserted = $this->db
                ->insertInto($name, $columns)
                ->values($columns)
                ->exec();

        if (!is_null($entity))
            $this->checkNewIdentity($entity, $name);

        return $isInserted;
    }

    protected function checkNewIdentity($entity, $name)
    {
        $identity = null;
        try {
            $identity = $this->db->getConnection()->lastInsertId();
        } catch (PDOException $e) {
            //some drivers may throw an exception here, it is just irrelevant
            return false;
        }
        if (!$identity)
            return false;

        $primaryName = $this->getStyle()->primaryFromTable($name);
        $entity->$primaryName = $identity;
        return true;
    }

    public function markTracked($entity, $name, $id = null)
    {
        $primaryName = $this->getStyle()->primaryFromTable($name);
        $id = $entity->{$primaryName};
        $this->tracked[$entity] = array(
            'name' => $name,
            'table_name' => $name,
            'entity_class' => $this->getStyle()->tableToEntity($name),
            $primaryName => &$id,
            'cols' => $this->extractColumns($entity, $name)
        );
        return true;
    }

    public function isTracked($entity)
    {
        return $this->tracked->contains($entity);
    }

    public function getTracked($name, $id)
    {
        $primaryName = $this->getStyle()->primaryFromTable($name);
        foreach ($this->tracked as $entity)
            if ($this->tracked[$entity][$primaryName] == $id
                    && $this->tracked[$entity]['name'] === $name)
                return $entity;

        return false;
    }

    protected function createStatement(Collection $collection, Sql $sqlExtra = null)
    {
        $query = $this->generateQuery($collection);
        if ($sqlExtra)
            $query->appendQuery($sqlExtra);
        $statement = $this->db->prepare((string) $query, PDO::FETCH_NUM);
        $statement->execute($query->getParams());
        return $statement;
    }

    protected function parseHydrated(SplObjectStorage $hydrated)
    {
        $this->tracked->addAll($hydrated);
        $hydrated->rewind();
        return $hydrated->current();
    }

    protected function generateQuery(Collection $collection)
    {
        $collections = iterator_to_array(CollectionIterator::recursive($collection), true);
        $sql = new Sql;

        $this->buildSelectStatement($sql, $collections);
        $this->buildTables($sql, $collections);

        return $sql;
    }

    protected function extractColumns($entity, $name)
    {
        $primaryName = $this->getStyle()->primaryFromTable($name);
        $cols = get_object_vars($entity);

        foreach ($cols as &$c)
            if (is_object($c))
                $c = $c->{$primaryName};

        return $cols;
    }

    protected function buildSelectStatement(Sql $sql, $collections)
    {
        $selectTable = array_keys($collections);
        foreach ($selectTable as &$ts)
            $ts = "$ts.*";

        return $sql->select($selectTable);
    }

    protected function buildTables(Sql $sql, $collections)
    {
        $conditions = $aliases = array();

        foreach ($collections as $alias => $collection)
            $this->parseCollection($sql, $collection, $alias, $aliases, $conditions);

        return $sql->where($conditions);
    }

    protected function parseConditions(&$conditions, $collection, $alias)
    {
        $entity = $collection->getName();
        $originalConditions = $collection->getCondition();
        $parsedConditions = array();
        $aliasedPk = $alias . '.' . $this->getStyle()->primaryFromTable($entity);

        if (is_scalar($originalConditions))
            $parsedConditions = array($aliasedPk => $originalConditions);
        elseif (is_array($originalConditions))
            foreach ($originalConditions as $column => $value)
                if (is_numeric($column))
                    $parsedConditions[$column] = preg_replace("/{$entity}[.](\w+)/", "$alias.$1", $value);
                else
                    $parsedConditions["$alias.$column"] = $value;

        return $parsedConditions;
    }

    protected function parseCollection(Sql $sql, Collection $collection, $alias, &$aliases, &$conditions)
    {
        $entity = $collection->getName();
        $parent = $collection->getParentName();
        $next = $collection->getNextName();

        $parentAlias = $parent ? $aliases[$parent] : null;
        $aliases[$entity] = $alias;
        $parsedConditions = $this->parseConditions($conditions, $collection, $alias);

        if (!empty($parsedConditions))
            $conditions[] = $parsedConditions;

        if (is_null($parentAlias))
            return $sql->from($entity);
        elseif ($collection->isRequired())
            $sql->innerJoin($entity);
        else
            $sql->leftJoin($entity);

        if ($alias !== $entity)
            $sql->as($alias);

        $aliasedPk = $alias . '.' . $this->getStyle()->primaryFromTable($entity);
        $aliasedParentPk = $parentAlias . '.' . $this->getStyle()->primaryFromTable($parent);

        if ($entity === $this->getStyle()->manyFromLeftRight($parent, $next)
                || $entity === $this->getStyle()->manyFromLeftRight($next, $parent))
            return $sql->on(
                array(
                    $alias . '.' . $this->getStyle()->foreignFromTable($parent) => $aliasedParentPk
                )
            );
        else
            return $sql->on(
                array(
                    $parentAlias . '.' . $this->getStyle()->foreignFromTable($entity) => $aliasedPk
                )
            );
    }

    protected function fetchHydrated(Collection $collection, PDOStatement $statement)
    {
        if (!$collection->hasMore())
            return $this->fetchSingle($collection, $statement);
        else
            return $this->fetchMulti($collection, $statement);
    }

    protected function fetchSingle(Collection $collection, PDOStatement $statement)
    {
        $name = $collection->getName();
        $primaryName = $this->getStyle()->primaryFromTable($name);
        $entityClass = $this->entityNamespace . $this->getStyle()->tableToEntity($name);
        $entityClass = class_exists($entityClass) ? $entityClass : '\stdClass';
        $statement->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $entityClass);
        $row = $statement->fetch();

        if (!$row)
            return false;

        $entities = new SplObjectStorage();
        $entities[$row] = array(
            'name' => $name,
            'table_name' => $name,
            'entity_class' => $entityClass,
            $primaryName => $row->{$primaryName},
            'cols' => $this->extractColumns($row, $name)
        );

        return $entities;
    }

    protected function fetchMulti(Collection $collection, PDOStatement $statement)
    {
        $name = $collection->getName();
        $entityInstance = null;
        $collections = CollectionIterator::recursive($collection);
        $row = $statement->fetch(PDO::FETCH_NUM);

        if (!$row)
            return false;

        $entities = new SplObjectStorage();

        foreach ($row as $n => $value) {
            $meta = $statement->getColumnMeta($n);

            if ($this->getStyle()->primaryFromTable($meta['table']) === $meta['name']) {

                if (0 !== $n)
                    $entities[$entityInstance] = array(
                        'name' => $tableName,
                        'table_name' => $tableName,
                        'entity_class' => $entityClass,
                        $primaryName => $entityInstance->{$primaryName},
                        'cols' => $this->extractColumns(
                            $entityInstance, $tableName
                        )
                    );

                $collections->next();
                $tableName = $collections->current()->getName();
                $primaryName = $this->getStyle()->primaryFromTable($tableName);
                $entityClass = $this->entityNamespace . $this->getStyle()->tableToEntity($tableName);
                $entityClass = class_exists($entityClass) ? $entityClass : '\stdClass';
                $entityInstance = new $entityClass;
            }
            $entityInstance->{$meta['name']} = $value;
        }

        if (!empty($entities))
            $entities[$entityInstance] = array(
                'name' => $tableName,
                'table_name' => $tableName,
                'entity_class' => $entityClass,
                $primaryName => $entityInstance->{$primaryName},
                'cols' => $this->extractColumns($entityInstance, $tableName)
            );

        $entitiesClone = clone $entities;

        foreach ($entities as $instance) {
            foreach ($instance as $field => &$v) {
                if ($this->getStyle()->isForeignColumn($field)) {
                    foreach ($entitiesClone as $sub) {
                        $tableName = $entities[$sub]['table_name'];
                        $primaryName = $this->getStyle()->primaryFromTable($tableName);
                        if ($entities[$sub]['name'] === $this->getStyle()->tableFromForeignColumn($field)
                                && $sub->{$primaryName} === $v) {
                            $v = $sub;
                        }
                    }
                }
            }
        }

        return $entities;
    }

    /**
     * @return  Respect\Relational\Styles\Stylable
     */
    public function getStyle()
    {
        if (null === $this->style) {
            $this->setStyle(new Styles\Standard());
        }
        return $this->style;
    }

    /**
     * @param   Respect\Relational\Styles$style
     * @return  Respect\Data\AbstractMapper
     */
    public function setStyle(Styles\Stylable $style)
    {
        $this->style = $style;
        return $this;
    }


}

