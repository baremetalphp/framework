<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Builder;
use BareMetalPHP\Support\Collection;

class HasOne
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey = 'id',
    ) {}

    protected function newQuery(): Builder
    {
        /** @var class-string<Model> $related */
        $related = $this->related;

        return $related::query();
    }

    /**
     * Lazy-load for a *single* parent.
     */
    public function getResults(): ?Model
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if ($localValue === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->foreignKey, '=', $localValue)
            ->first();
    }

    /**
     * Collect parent keys for eager loading.
     *
     * @param array<int, Model> $models
     * @return array<int, int|string>
     */
    public function addEagerConstraints(array $models): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $value = $model->getAttribute($this->localKey);

            if ($value !== null) {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Run the eager load query.
     */
    public function getEager(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection();
        }

        return $this->newQuery()
            ->whereIn($this->foreignKey, $keys)
            ->get();
    }

    /**
     * Match the eager results back onto their parent models (1:1).
     *
     * @param array<int, Model> $models
     */
    public function match(array $models, Collection $results, string $relation): void
    {
        $dictionary = [];

        foreach ($results as $result) {
            if (!$result instanceof Model) {
                continue;
            }

            $fk = $result->getAttribute($this->foreignKey);

            if ($fk === null) {
                continue;
            }

            // Only one related model per parent key for hasOne.
            $dictionary[$fk] = $result;
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $localValue = $model->getAttribute($this->localKey);

            $related = ($localValue !== null && isset($dictionary[$localValue]))
                ? $dictionary[$localValue]
                : null;

            $model->setRelation($relation, $related);
        }
    }
}
