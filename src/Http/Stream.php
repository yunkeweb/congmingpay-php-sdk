<?php

declare(strict_types=1);

namespace CongmingPay\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    public function __construct(string $contents = '')
    {
        $resource = fopen('php://temp', 'rb+');
        if ($resource === false) {
            throw new RuntimeException('Unable to create stream.');
        }

        $this->resource = $resource;
        if ($contents !== '') {
            fwrite($this->resource, $contents);
            rewind($this->resource);
        }
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            $this->rewind();

            return $this->getContents();
        } catch (RuntimeException $exception) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats === false ? null : $stats['size'];
    }

    public function tell(): int
    {
        $this->assertAttached();
        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $metadata = stream_get_meta_data($this->resource);

        return (bool) $metadata['seekable'];
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->assertAttached();
        if (!$this->isSeekable() || fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $mode = stream_get_meta_data($this->resource)['mode'];

        return strpbrk($mode, 'waxc+') !== false;
    }

    public function write($string): int
    {
        $this->assertAttached();
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $written = fwrite($this->resource, $string);
        if ($written === false) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $mode = stream_get_meta_data($this->resource)['mode'];

        return strpbrk($mode, 'r+') !== false;
    }

    public function read($length): string
    {
        $this->assertAttached();
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $data;
    }

    public function getContents(): string
    {
        $this->assertAttached();
        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $contents;
    }

    public function getMetadata($key = null)
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->resource);

        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }

    private function assertAttached(): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }
    }
}
