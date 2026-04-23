<?php

declare(strict_types=1);

namespace CongmingPay;

use InvalidArgumentException;

final class Config
{
    private string $baseUri;

    private ?string $programId;

    private string $shopId;

    private string $secretKey;

    private int $timeout;

    private bool $verifySsl;

    public function __construct(string $baseUri, ?string $programId, string $shopId, string $secretKey, int $timeout = 30, bool $verifySsl = true)
    {
        $baseUri = rtrim(trim($baseUri), '/');
        if ($baseUri === '') {
            throw new InvalidArgumentException('Config baseUri cannot be empty.');
        }
        if ($shopId === '' || $secretKey === '') {
            throw new InvalidArgumentException('Config shopId and secretKey cannot be empty.');
        }

        $this->baseUri = $baseUri;
        $this->programId = $programId === '' ? null : $programId;
        $this->shopId = $shopId;
        $this->secretKey = $secretKey;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getProgramId(): ?string
    {
        return $this->programId;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function shouldVerifySsl(): bool
    {
        return $this->verifySsl;
    }
}
