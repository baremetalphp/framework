<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Builder;
use BareMetalPHP\Support\Collection;

class MorphMany
{
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $morphType, // e.g. "commentable_Type"
        protected string $morphId, // e.g. "commentable_id"
        protected string $localKey = 'id',
    ){}


    /**
     * 
     * @return Builder
     */
    public function newQuery(): Builder
    {
        /**
         * @var class-string<Model> $related
         */
        $related = $this->related;

        return $related::query();
    }

    protected function morphClass(): string
    {
        return $this->parent->getMorphClass();
    }

    public function getResults(): Collection
    {
        $id = $this->parent->getAttribute($this->localKey);

        if ($id === null) {
            return new Collection();
        }

        return $this->newQuery()
            ->where($this->morphType, '=', $this->morphClass())
            ->where($this->morphId, '=', $id)
            ->get();
    }
    /**
     * Collect ids for eager constraints.
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
     * Run eager load query
     * @param array $keys
     * @return Collection
     */
    public function getEager(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection();
        }

        return $this->newQuery()
            ->where($this->morphType, '=', $this->morphClass())
            ->whereIn($this->morphId, $keys)
            ->get();
    }
    /**
     * Match results to parents.
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

            $id = $result->getAttribute($this->morphId);

            if ($id === null) {
                continue;
            }

            $dictionary[$id][] = $result;
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $localId = $model->getAttribute($this->localKey);

            $items = $localId !== null && isset($dictionary[$localId])
                ? new Collection($dictionary[$localId])
                : new Collection();

            $model->setRelation($relation, $items);
        }
    }
}