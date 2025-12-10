<?php

declare(strict_types= 1);

namespace BareMetalPHP\Serialization;

use BareMetalPHP\Serialization\NormalizerInterface;
use BareMetalPHP\Support\Collection;
use BareMetalPHP\Database\Model;
use DateTimeInterface;
use JsonSerializable;

class DefaultNormalizer implements NormalizerInterface
{
    public function supports(mixed $data, array $context = []): bool
    {
        // this is a catch-all normalizer --> always says yes
        return true; 
    }

    public function normalize(mixed $data, array $context = []): mixed
    {
        // null / scalar
        if ($data === null || is_scalar($data)) {
            return $data;
        }

        // DateTime -> string
        if ($data instanceof DateTimeInterface) {
            $format = $context['datetime_format'] ?? DateTimeInterface::RFC3339;
            return $data->format($format);
        }

        if ($data instanceof Collection) {
            return $data->toArray();
        }

        if ($data instanceof Model) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            $serialized = $data->jsonSerialize();
            // in case jsonSerialize() returns objects, recurse
            return $this->normalize($serialized, $context);
        }

        // Arrays: normalize each element
        if (is_array($data)) {
            $normalized = [];
            foreach ($data as $key => $value) {
                $normalized[$key] = $this->normalize($value, $context);
            }

            return $normalized;
        }

        // generic object: if it has toArray(), use it
        if (is_object($data) && method_exists($data, 'toArray')) {
            $array = $data->toArray();
            return $this->normalize($array, $context);
        }

        // fallback: cast public properties
        if (is_object($data)) {
            return $this->normalize(get_object_vars($data), $context);
        }

        // worse-case fallback
        return (string) $data;
    }
}