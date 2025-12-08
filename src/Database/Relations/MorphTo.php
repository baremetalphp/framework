<?php

declare(strict_types=1);

namespace BareMetalPHP\Database\Relations;

use BareMetalPHP\Database\Model;

class MorphTo
{
    public function __construct(
        protected Model $child,
        protected string $name,       // e.g. "commentable"
        protected ?string $typeColumn = null,
        protected ?string $idColumn = null,
    ) {
        $this->typeColumn ??= $name . '_type';
        $this->idColumn   ??= $name . '_id';
    }

    public function getResults(): ?Model
    {
        $type = $this->child->getAttribute($this->typeColumn);
        $id   = $this->child->getAttribute($this->idColumn);

        if (!$type || !$id) {
            return null;
        }

        // For now, we treat $type as a fully-qualified class-name.
        // Later we can add a morphMap (alias => class) if needed.
        if (!is_string($type) || !class_exists($type)) {
            return null;
        }

        /** @var class-string<Model> $related */
        $related = $type;

        return $related::query()
            ->where('id', '=', $id) // assumes "id" primary; can be extended later
            ->first();
    }
}
