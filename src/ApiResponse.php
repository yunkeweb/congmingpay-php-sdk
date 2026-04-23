<?php

declare(strict_types=1);

namespace CongmingPay;

use Psr\Http\Message\ResponseInterface;

final class ApiResponse
{
    private ResponseInterface $response;

    /** @var array<string, mixed>|null */
    private ?array $data;

    /** @param array<string, mixed>|null $data */
    public function __construct(ResponseInterface $response, ?array $data)
    {
        $this->response = $response;
        $this->data = $data;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getBody(): string
    {
        return (string) $this->response->getBody();
    }

    /** @return array<string, mixed>|null */
    public function getData(): ?array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data ?? [];
    }

    public function isSuccessful(): bool
    {
        return $this->response->getStatusCode() >= 200
            && $this->response->getStatusCode() < 300
            && isset($this->data['result_code'])
            && strtolower((string) $this->data['result_code']) === 'success';
    }
}
