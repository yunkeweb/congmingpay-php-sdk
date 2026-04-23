<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response implements ResponseInterface
{
    private string $protocolVersion;

    private int $statusCode;

    private string $reasonPhrase;

    /** @var array<string, string[]> */
    private array $headers;

    /** @var array<string, string> */
    private array $headerNames;

    private StreamInterface $body;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(int $statusCode, array $headers, string $body, string $reasonPhrase = '', string $protocolVersion = '1.1')
    {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = [];
        $this->headerNames = [];
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        $this->body = new Stream($body);
        $this->protocolVersion = $protocolVersion;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): self
    {
        $new = clone $this;
        $new->protocolVersion = (string) $version;

        return $new;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        $code = (int) $code;
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('HTTP status code must be between 100 and 599.');
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = (string) $reasonPhrase;

        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /** @return array<string, string[]> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower((string) $name)]);
    }

    /** @return string[] */
    public function getHeader($name): array
    {
        $lower = strtolower((string) $name);
        if (!isset($this->headerNames[$lower])) {
            return [];
        }

        return $this->headers[$this->headerNames[$lower]];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): self
    {
        $new = clone $this;
        $new->removeHeader((string) $name);
        $new->setHeader((string) $name, $value);

        return $new;
    }

    public function withAddedHeader($name, $value): self
    {
        $new = clone $this;
        $name = (string) $name;
        $lower = strtolower($name);
        $values = $new->normalizeHeaderValue($value);
        if (isset($new->headerNames[$lower])) {
            $original = $new->headerNames[$lower];
            $new->headers[$original] = array_merge($new->headers[$original], $values);

            return $new;
        }

        $new->headers[$name] = $values;
        $new->headerNames[$lower] = $name;

        return $new;
    }

    public function withoutHeader($name): self
    {
        $new = clone $this;
        $new->removeHeader((string) $name);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): self
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /** @param string|string[] $value */
    private function setHeader(string $name, $value): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Header name cannot be empty.');
        }

        $this->removeHeader($name);
        $this->headers[$name] = $this->normalizeHeaderValue($value);
        $this->headerNames[strtolower($name)] = $name;
    }

    private function removeHeader(string $name): void
    {
        $lower = strtolower($name);
        if (!isset($this->headerNames[$lower])) {
            return;
        }

        $original = $this->headerNames[$lower];
        unset($this->headers[$original], $this->headerNames[$lower]);
    }

    /**
     * @param string|string[] $value
     * @return string[]
     */
    private function normalizeHeaderValue($value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_map(static function ($item): string {
            return (string) $item;
        }, $values);
    }
}
