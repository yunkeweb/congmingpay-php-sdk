<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use CongmingPay\Config;
use CongmingPay\Exception\HttpException;
use CongmingPay\Exception\InvalidResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CurlHttpClient implements HttpClientInterface
{
    private Config $config;

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

        $request = new Request('POST', $url, array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers), $body);
        $response = $this->sendRequest($request);
        if (!$response instanceof Response) {
            throw new InvalidResponseException('Unexpected response implementation.');
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new HttpException(sprintf('Unexpected HTTP status code %d: %s', $response->getStatusCode(), $response->getRawBody()));
        }

        return $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $responseHeaders = [];
        $reasonPhrase = '';
        $curl = curl_init((string) $request->getUri());
        if ($curl === false) {
            throw new HttpException('Failed to initialize curl.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_POSTFIELDS => (string) $request->getBody(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeout(),
            CURLOPT_SSL_VERIFYPEER => $this->config->shouldVerifySsl(),
            CURLOPT_SSL_VERIFYHOST => $this->config->shouldVerifySsl() ? 2 : 0,
            CURLOPT_HTTPHEADER => $this->formatHeaders($request->getHeaders()),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders, &$reasonPhrase): int {
                $length = strlen($header);
                if (preg_match('/^HTTP\/\S+\s+\d{3}\s*(.*)$/', trim($header), $matches) === 1) {
                    $reasonPhrase = $matches[1] ?? '';

                    return $length;
                }

                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = trim($parts[0]);
                    $responseHeaders[$name][] = trim($parts[1]);
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

        $json = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json = null;
        }

        return new Response($statusCode, $responseHeaders, (string) $raw, is_array($json) ? $json : null, $reasonPhrase);
    }

    /**
     * @param array<string, string> $headers
     * @return string[]
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            foreach ((array) $value as $item) {
                $formatted[] = $name . ': ' . $item;
            }
        }

        return $formatted;
    }
}
