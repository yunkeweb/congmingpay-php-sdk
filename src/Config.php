<?php

declare(strict_types=1);

namespace CongmingPay;

use InvalidArgumentException;

final class Config
{
    /** @var string */
    private $baseUri;

    /** @var string */
    private $programId;

    /** @var string */
    private $shopId;

    /** @var string */
    private $secretKey;

    /** @var int */
    private $timeout;

    /** @var bool */
    private $verifySsl;

    public function __construct(string $baseUri, string $programId, string $shopId, string $secretKey, int $timeout = 30, bool $verifySsl = true)
    {
        $baseUri = rtrim(trim($baseUri), '/');
        if ($baseUri === '') {
            throw new InvalidArgumentException('Config baseUri cannot be empty.');
        }
        if ($programId === '' || $shopId === '' || $secretKey === '') {
            throw new InvalidArgumentException('Config programId, shopId and secretKey cannot be empty.');
        }

        $this->baseUri = $baseUri;
        $this->programId = $programId;
        $this->shopId = $shopId;
        $this->secretKey = $secretKey;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getProgramId(): string
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
