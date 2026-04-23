<?php

declare(strict_types=1);

namespace CongmingPay\Http;

final class Response
{
    /** @var int */
    private $statusCode;

    /** @var array<string, string> */
    private $headers;

    /** @var string */
    private $body;

    /** @var array<string, mixed>|null */
    private $json;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    public function __construct(int $statusCode, array $headers, string $body, ?array $json)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->json = $json;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed>|null */
    public function getJson(): ?array
    {
        return $this->json;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->json ?? [];
    }

    public function isSuccess(): bool
    {
        return isset($this->json['result_code']) && strtolower((string) $this->json['result_code']) === 'success';
    }
}
