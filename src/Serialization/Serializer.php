<?php

declare(strict_types=1);

namespace BareMetalPHP\Serialization;

use RuntimeException;

class Serializer
{
    /**
     * 
     * @param NormalizerInterface[] $normalizers
     * @param EncoderInterface[] $encoders
     */
    public function __construct(
        protected array $normalizers = [],
        protected array $encoders = [],
    ) {}

    /**
     * Normalize, then encode data into given format.
     * @param mixed $data
     * @param string $format
     * @param array<string, mixed> $context
     * @return string
     */
    public function serialize(mixed $data, string $format = 'json', array $context = []): string
    {
        $normalized = $this->normalize($data, $context);
        $encoder = $this->getEncoderForFormat($format);

        return $encoder->encode($normalized, $context);
    }

    /**
     * Just normalize without encoding.
     * @param mixed $data
     * @param array<string, mixed> $context
     * @return mixed
     */
    public function normalize(mixed $data, array $context = []): mixed
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($data, $context)) {
                return $normalizer->normalize($data, $context);
            }
        }

        throw new RuntimeException("No normalizer was able to handle the given data.");
    }

    protected function getEncoderForFormat(string $format): EncoderInterface
    {
        foreach ($this->encoders as $encoder) {
            if ($encoder->supportsFormat($format)) {
                return $encoder;
            }
        }

        throw new RuntimeException("No encoder registered for format [{$format}].");
    }

}