<?php

declare(strict_types= 1);

namespace BareMetalPHP\Serialization;

use IteratorAggregate;
use Traversable;
use JsonSerializable;

class JsonResourceCollection implements IteratorAggregate, JsonSerializable
{

    /**
     * @param class-string<JsonResource> $resourceClass
     * @param iterable<mixed> $items
     */
    public function __construct(
        protected string $resourceClass,
        protected iterable $items
    ) {}

    public function getIterator(): Traversable
    {
        foreach ($this->items as $item) {
            yield new $this->resourceClass($item);
        }
    }

    public function jsonSerialize(): mixed
    {
        $result = [];
        foreach ($this->getIterator() as $resource) {
            /** @var JsonResource $resource */
            $result[] = $resource->jsonSerialize();
        }
        return $result;
    }
}