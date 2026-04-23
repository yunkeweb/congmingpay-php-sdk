<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use Psr\Http\Client\ClientInterface;

interface HttpClientInterface extends ClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $payload, array $headers = []): Response;
}
