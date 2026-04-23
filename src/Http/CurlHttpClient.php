<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use CongmingPay\Config;
use CongmingPay\Exception\HttpException;
use CongmingPay\Exception\InvalidResponseException;

final class CurlHttpClient implements HttpClientInterface
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function postJson(string $url, array $payload, array $headers = []): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new InvalidResponseException('Failed to encode request payload: ' . json_last_error_msg());
        }

        $responseHeaders = [];
        $curl = curl_init($url);
        if ($curl === false) {
            throw new HttpException('Failed to initialize curl.');
        }

        $requestHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeout(),
            CURLOPT_SSL_VERIFYPEER => $this->config->shouldVerifySsl(),
            CURLOPT_SSL_VERIFYHOST => $this->config->shouldVerifySsl() ? 2 : 0,
            CURLOPT_HTTPHEADER => $this->formatHeaders($requestHeaders),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $message = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            throw new HttpException(sprintf('HTTP request failed [%d]: %s', $errno, $message));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpException(sprintf('Unexpected HTTP status code %d: %s', $statusCode, (string) $raw));
        }

        $json = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json = null;
        }

        return new Response($statusCode, $responseHeaders, (string) $raw, is_array($json) ? $json : null);
    }

    /**
     * @param array<string, string> $headers
     * @return string[]
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }
}
