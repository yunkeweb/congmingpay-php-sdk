<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use CongmingPay\Config;
use CongmingPay\Exception\HttpException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CurlHttpClient implements ClientInterface
{
    private Config $config;

    private LoggerInterface $logger;

    public function __construct(Config $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $responseHeaders = [];
        $reasonPhrase = '';
        $this->logger->info('Sending CongmingPay HTTP request.', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);

        $curl = curl_init((string) $request->getUri());
        if ($curl === false) {
            $this->logger->error('Failed to initialize cURL.');
            throw new HttpException('Failed to initialize curl.');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
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
        ];

        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $requestBody = (string) $body;
        if ($requestBody !== '') {
            $options[CURLOPT_POSTFIELDS] = $requestBody;
        }

        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $message = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            $this->logger->error('CongmingPay HTTP request failed.', [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'curl_errno' => $errno,
                'error' => $message,
            ]);
            throw new HttpException(sprintf('HTTP request failed [%d]: %s', $errno, $message));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $this->logger->info('CongmingPay HTTP response received.', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status_code' => $statusCode,
        ]);

        return new Response($statusCode, $responseHeaders, (string) $raw, $reasonPhrase);
    }

    /**
     * @param array<string, string[]> $headers
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
