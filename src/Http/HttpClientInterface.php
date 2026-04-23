<?php

declare(strict_types=1);

namespace CongmingPay\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $payload, array $headers = []): Response;
}
