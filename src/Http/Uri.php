<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid URI.');
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';

        if (isset($parts['user'])) {
            $this->userInfo = $parts['user'];
            if (isset($parts['pass'])) {
                $this->userInfo .= ':' . $parts['pass'];
            }
        }
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;
        if ($authority !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null && !$this->isDefaultPort()) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->isDefaultPort() ? null : $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): self
    {
        $scheme = (string) $scheme;
        if ($scheme !== '' && preg_match('/^[A-Za-z][A-Za-z0-9+\-.]*$/', $scheme) !== 1) {
            throw new InvalidArgumentException('Invalid URI scheme.');
        }

        $new = clone $this;
        $new->scheme = strtolower($scheme);

        return $new;
    }

    public function withUserInfo($user, $password = null): self
    {
        $new = clone $this;
        $new->userInfo = (string) $user;
        if ($password !== null) {
            $new->userInfo .= ':' . (string) $password;
        }

        return $new;
    }

    public function withHost($host): self
    {
        $host = (string) $host;
        if (preg_match('/[\s\/?#@:]/', $host) === 1) {
            throw new InvalidArgumentException('Invalid URI host.');
        }

        $new = clone $this;
        $new->host = strtolower($host);

        return $new;
    }

    public function withPort($port): self
    {
        if ($port === '') {
            throw new InvalidArgumentException('Invalid URI port.');
        }
        if (is_string($port) && $port !== '' && ctype_digit($port)) {
            $port = (int) $port;
        }

        if ($port !== null && !is_int($port)) {
            throw new InvalidArgumentException('Invalid URI port.');
        }
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('Invalid URI port.');
        }

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    public function withPath($path): self
    {
        $new = clone $this;
        $new->path = (string) $path;

        return $new;
    }

    public function withQuery($query): self
    {
        $new = clone $this;
        $new->query = ltrim((string) $query, '?');

        return $new;
    }

    public function withFragment($fragment): self
    {
        $new = clone $this;
        $new->fragment = ltrim((string) $fragment, '#');

        return $new;
    }

    private function isDefaultPort(): bool
    {
        return ($this->scheme === 'http' && $this->port === 80)
            || ($this->scheme === 'https' && $this->port === 443);
    }
}
