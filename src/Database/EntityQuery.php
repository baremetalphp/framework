<?php

namespace BareMetalPHP\Database;

use BareMetalPHP\Support\Collection;
class EntityQuery
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public function __construct(
        protected string $entityClass,
        protected Builder $builder,
        protected EntityManager $em
    ) {
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->builder->where($column, $operator, $value);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->builder->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->builder->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->builder->offset($offset);
        return $this;
    }

    /**
     * Get all matching entities.
     *
     * @return Collection<int,Entity>
     */
    public function get(): Collection
    {
        $rows = $this->builder->getRows();

        $entities = array_map(function (array $row): Entity {
            $class = $this->entityClass;
            /** @var Entity $entity */
            $entity = new $class();
            $entity->hydrateFromRow($row);
            return $entity;
        }, $rows);

        return new Collection($entities);
    }

    /**
     * Get the first matching entity or null.
     */
    public function first(): ?Entity
    {
        $this->builder->limit(1);
        $rows = $this->builder->getRows();

        if (empty($rows)) {
            return null;
        }

        $class = $this->entityClass;
        /** @var Entity $entity */
        $entity = new $class();
        $entity->hydrateFromRow($rows[0]);

        return $entity;
    }
}
