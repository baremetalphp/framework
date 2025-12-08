<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Builder;
use BareMetalPHP\Support\Collection;

/**
 * HasMany Relationship
 */
class HasMany extends Relation
{ 
    /**
     * Create HasMany Relationship Object
     * @param Model $parent
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey = 'id',
     */
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey = 'id',
    ) {
        parent::__construct($parent, $related);
    }

    /**
     * Lazy-load results for a *single* parent model.
     * @return Collection
     */
    public function getResults(): Collection
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if ($localValue === null) {
            return new Collection();
        }

        return $this->newQuery()->where($this->foreignKey, '=', $localValue)
        ->get();
    }

    /**
     * Prepare constraints for eager loading accross a collection of parents.
     * 
     * @param array<int, Model> $models
     * @return array
     */
    public function addEagerConstraints(array $models): array
    {
        return $this->getKeys($models, $this->localKey);
    }

    /**
     * Run the eager load query.
     * 
     * @param array>int, int|string> $keys
     * @return Collection
     */
    public function getEager(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection();
        }

        return $this->newQuery()->whereIn($this->foreignKey, $keys)->get();
    }

    /**
     * Match a single related model back to each parent.
     * @param array<int, Model> $models
     * @param Collection $results
     * @param string $relation
     * @return void
     */
    public function match(array $models, Collection $results, string $relation): void
    {
        // foreignKeyValue => single related model
        $dictionary = [];

        foreach ($results as $result) {
            if (! $result instanceof Model) {
                continue;
            }

            $fkValue = $result->getAttribute($this->foreignKey);

            if ($fkValue === null) {
                continue;
            }

            // Only one per key for hasOne
            $dictionary[$fkValue] = $result;
        }

        foreach ($models as $model) {
            if (! $model instanceof Model) {
                continue;
            }

            $localKeyValue = $model->getAttribute($this->localKey);

            $related = ($localKeyValue !== null && isset($dictionary[$localKeyValue]))
                ? $dictionary[$localKeyValue]
                : null;

            $model->setRelation($relation, $related);
        }
    }
}