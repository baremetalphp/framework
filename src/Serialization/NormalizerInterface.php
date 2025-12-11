<?php

declare(strict_types=1);

namespace BareMetalPHP\Serialization;

interface NormalizerInterface
{
    /**
     * Whether this normalizer can handle the given data.
     * 
     * @param mixed $data
     * @param array<string, mixed> $context
     * @return bool
     */
    public function supports(mixed $data, array $context = []): bool;

    /**
     * Normalize the data into arrays/scalars/other "simple" structures.
     * @param mixed $data
     * @param array<string, mixed> $context
     * @return mixed
     */
    public function normalize(mixed $data, array $context = []): mixed;
}