<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Builder;
use BareMetalPHP\Support\Collection;

abstract class Relation
{
    public function __construct(
        protected Model $parent,
        protected string $related
    ) {}

    protected function newQuery(): Builder
    {
        /**
         * @var class-string<Model> $related
         */
        $related = $this->related;

        return $related::query();
    }
    /**
     * Helper to extract unique, non-null key values from a set of models.
     *
     * @param  array<int, Model>  $models
     * @return array<int, int|string>
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (! $model instanceof Model) {
                continue;
            }

            $value = $model->getAttribute($key);

            if ($value !== null) {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Lazy-load the relationship result for a *single* parent.
     *
     * For:
     *  - hasMany:      Collection
     *  - hasOne:       Model|null
     *  - belongsTo:    Model|null
     */
    abstract public function getResults(): mixed;

    /**
     * Collect key values needed for eager loading from a set of parents.
     *
     * Example:
     *  - hasMany/hasOne: parents' localKey values
     *  - belongsTo:      children’s foreignKey values
     *
     * @param  array<int, Model>  $models
     * @return array<int, int|string>
     */
    abstract public function addEagerConstraints(array $models): array;

    /**
     * Run the eager load query and return related models.
     *
     * @param  array<int, int|string> $keys
     */
    abstract public function getEager(array $keys): Collection;

    /**
     * Match the eager-loaded results back onto the given parent models.
     *
     * @param  array<int, Model> $models
     */
    abstract public function match(array $models, Collection $results, string $relation): void;
}