<?php

declare(strict_types=1);

namespace BareMetalPHP\Serialization;

use RuntimeException;

class JsonEncoder implements EncoderInterface
{
    /**
     * Checks if $format === 'json' 
     * 
     * @param string $format
     * @return bool
     */
    public function supportsFormat(string $format): bool
    {
        return $format === 'json';
    }

    public function encode(mixed $data, array $context = []): string
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (($context['pretty'] ?? false) === true) {
            $options |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON Encoding error: ' . json_last_error_msg());
        }

        return $json;
    }
}