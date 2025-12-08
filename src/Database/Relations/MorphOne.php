<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Support\Collection;

class MorphOne extends MorphMany
{
    /**
     * Lazy-load single related model.
     */
    public function getResults(): ?Model
    {
        $results = parent::getResults();

        /** @var Model|null $first */
        $first = $results->first();

        return $first;
    }

    /**
     * Match a single related model to each parent.
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

            // Only one per parent
            $dictionary[$id] = $result;
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $localId = $model->getAttribute($this->localKey);

            $related = ($localId !== null && isset($dictionary[$localId]))
                ? $dictionary[$localId]
                : null;

            $model->setRelation($relation, $related);
        }
    }
}
