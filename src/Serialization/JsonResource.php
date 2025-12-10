<?php

declare(strict_types= 1);

namespace BareMetalPHP\Serialization;

use BareMetalPHP\Support\Collection;
use JsonSerializable;

abstract class JsonResource implements JsonSerializable
{
    public function __construct(
        protected mixed $resource
    ) {}

    /**
     * Transform underlying resource into an array.
     * 
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    public function jsonSerialize(): mixed
    {
        $data = $this->toArray();

        if ($data instanceof Collection) {
            return $data->toArray();
        }

        return $data;
    }

    /**
     * Convenience method to wrap model/resource iterable
     * 
     * @param iterable<mixed> $items
     * @return JsonResourceCollection
     */
    public static function collection(iterable $items): JsonResource
    {
        return new JsonResourceCollection(static::class, $items);
    }
}