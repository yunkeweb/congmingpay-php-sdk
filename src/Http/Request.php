<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class Request implements RequestInterface
{
    private string $method;
    private UriInterface $uri;
    private string $requestTarget = '';
    private string $protocolVersion = '1.1';

    /** @var array<string, string[]> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $headerNames = [];

    private StreamInterface $body;

    /**
     * @param string|UriInterface $uri
     * @param array<string, string|string[]> $headers
     */
    public function __construct(string $method, $uri, array $headers = [], string $body = '')
    {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        $this->body = new Stream($body);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget($requestTarget): self
    {
        if (preg_match('/\s/', (string) $requestTarget) === 1) {
            throw new InvalidArgumentException('Request target cannot contain whitespace.');
        }

        $new = clone $this;
        $new->requestTarget = (string) $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): self
    {
        $new = clone $this;
        $new->method = strtoupper((string) $method);

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $new = clone $this;
        $new->uri = $uri;
        if (!$preserveHost && $uri->getHost() !== '') {
            $new = $new->withHeader('Host', $uri->getHost());
        }

        return $new;
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
        return implode(',', $this->getHeader($name));
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
