<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Builder;
use BareMetalPHP\Support\Collection;

class BelongsTo extends Relation
{
    public function __construct(
        protected Model $child,
        protected string $related,
        protected string $foreignKey,
        protected string $ownerKey = 'id',
    ) {
        parent::__construct($child, $related);
    }

    /**
     * Lazy load result for a *single* child model
     */
    public function getResults(): ?Model
    {
        $foreignValue = $this->child->getAttribute($this->foreignKey);

        if ($foreignValue === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->ownerKey, '=', $foreignValue)
            ->first();
    }

    /**
     * Collect foreign keys for eager loading
     * @param array $models
     * @return array
     */
    public function addEagerConstraints(array $models): array
    {
        return $this->getKeys($models, $this->foreignKey);
    }

    /**
     * Run the eager load query for many children.
     * @param array $keys
     * @return void
     */
    public function getEager(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection();
        }

        return $this->newQuery()
            ->whereIn($this->ownerKey, $keys)
            ->get();
    }

    /**
     * Match eager-loaded parents bacK to child models.
     * @param  array<int, Model>  $models
     * @param Collection $results
     * @param string $relation
     * @return void
     */
    public function match(array $models, Collection $results, string $relation): void
    {
        // index parents by owner key
        $dictionary = [];

        foreach ($results as $parent) {
            if (! $parent instanceof Model) {
                continue;
            }

            $ownerValue = $parent->getAttribute($this->ownerKey);

            if ($ownerValue === null) {
                continue;
            }

            $dictionary[$ownerValue] = $parent;
        }
        
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $foreignValue = $model->getAttribute($this->foreignKey);

            $related = ($foreignValue !== null && isset($dictionary[$foreignValue]))
                ? $dictionary[$foreignValue]
                : null;

            $model->setRelation($relation, $related);
        }
    }
}