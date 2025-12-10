<?php

declare(strict_types=1);

namespace BareMetalPHP\Serialization;

interface EncoderInterface
{
    /**
     * Encode normalized data into a string representation.
     * 
     * @param string $data  Already normalized data (arrays, scalars, etc.)
     * @param array<string, mixed> $context
     * @return string
     */
    public function encode(string $data, array $context = []): string;

    /**
     * Return true if this encoder supports the given format (e.g. "json").
     * @param string $format
     * @return bool
     */
    public function supportsFormat(string $format): bool;
}