<?php

namespace BareMetalPHP\Http;

use BareMetalPHP\Application;
use BareMetalPHP\Serialization\Serializer;

class Response
{
    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = []
    ){}

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value)
        {
            header($name . ': '.$value);
        }

        echo $this->content;
    }

    public static function make(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function json(
        mixed $data,
        int $status = 200,
        array $headers = [],
        array $context = []
    ): self {
        $app = Application::getInstance();
        $content = null;

        if ($app) {
            try {
                /** @var Serializer $serializer */
                $serializer = $app->make(Serializer::class);
                $content = $serializer->serialize($data, 'json', $context);
            } catch (\Throwable $e) {
                // fallback to bare json_encode if the serializer is not available
                $content = json_encode($data);
            }
        } else {
            $content = json_encode($data);
        }

        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';

        return new static((string) $content, $status, $headers);
    }

    
}