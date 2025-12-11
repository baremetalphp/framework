<?php

namespace BareMetalPHP\Database;

use BareMetalPHP\Database\ConnectionManager;

/**
 * EntityManager is a thin Data Mapper coordinator.
 *
 * Uses ConnectionManager and Builder to perform queries and
 * hydrates Entity objects from rows.
 */
class EntityManager
{
    public function __construct(
        protected ConnectionManager $connections
    ) {}

    /**
     * Get a typed query builder for the given entity class.
     *
     * @param string $entityClass
     * @return EntityQuery
     */
    public function for(string $entityClass): EntityQuery
    {
        $connection = $this->connections->connection();
        $pdo = $connection->pdo();

        $table = $entityClass::table();

        // modelClass is null here, Data Mapper handles hydration
        $builder = new Builder($pdo, $table, null, $connectionl);

        return new EntityQuery($entityClass, $builder, $this);
    }

    public function find(string $entityClass, int|string $id): ?Entity
    {
        $query = $this->for($entityClass);

        $pk = $entityClass::primaryKey();

        return $query->where($pk, '=', $id)->first();
    }

    public function save(Entity $entity): void
    {
        if ($entity->isNew()) {
            $this->insert($entity);
        } else {
            $this->update($entity);
        }
    }

    public function delete(Entity $entity): void
    {
        $entityClass = $entity::class;
        $table = $entityClass::table();
        $pk = $entityClass::primaryKey();

        $pkValue = $entity->{$pk} ?? null;
        if ($pkValue === null) {
            return;
        }

        $connection = $this->connections->connection();
        $pdo = $connection->pdo();
        $driver = $connection->getDriver();

        $quotedTable = $driver->quoteIdentifier($table);
        $quotedPk = $driver->quoteIdentifier($pk);

        $sql = "DELETE FROM {$quotedTable} WHERE {$quotedPk} = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $pkValue]);
    }

    protected function insert(Entity $entity): void
    {
        $entityClass = $entity::class;
        $table = $entityClass::table();
        $pk = $entityClass::primaryKey();

        $data = $entity->toArray();

        // Never insert explicit primary key if it is null
        if (!array_key_exists($pk, $data) || $data[$pk] === null) {
            unset($data[$pk]);
        }

        if (empty($data)) {
            return;
        }

        $connection = $this->connections->connection();
        $pdo = $connection->pdo();
        $driver = $connection->getDriver();

        $quotedTable = $driver->quoteIdentifier($table);

        $columns = array_keys($data);
        $placeholders = array_map(fn (string $col) => ':' . $col, $columns);

        $quotedColumns = array_map([$driver, 'quoteIdentifier'], $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quotedTable,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        // Set PK if auto-increment
        if (!isset($entity->{$pk})) {
            $lastId = $pdo->lastInsertId();
            if ($lastId !== false) {
                $entity->{$pk} = ctype_digit($lastId) ? (int) $lastId : $lastId;
            }
        }

        $entity->markClean();
    }

    protected function update(Entity $entity): void
    {
        $dirty = $entity->getDirty();
        if (empty($dirty)) {
            return;
        }

        $entityClass = $entity::class;
        $table = $entityClass::table();
        $pk = $entityClass::primaryKey();

        $pkValue = $entity->{$pk} ?? null;
        if ($pkValue === null) {
            throw new \RuntimeException(sprintf(
                'Cannot update %s without a primary key value.',
                $entityClass
            ));
        }

        // Never try to update the PK
        unset($dirty[$pk]);

        if (empty($dirty)) {
            return;
        }

        $connection = $this->connections->connection();
        $pdo = $connection->pdo();
        $driver = $connection->getDriver();

        $quotedTable = $driver->quoteIdentifier($table);

        $assignments = [];
        foreach (array_keys($dirty) as $column) {
            $assignments[] = $driver->quoteIdentifier($column) . ' = :' . $column;
        }

        $quotedPk = $driver->quoteIdentifier($pk);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :_pk',
            $quotedTable,
            implode(', ', $assignments),
            $quotedPk
        );

        $params = $dirty;
        $params['_pk'] = $pkValue;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $entity->markClean();
    }
}