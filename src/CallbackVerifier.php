<?php

declare(strict_types=1);

namespace CongmingPay;

use CongmingPay\Support\Signer;

final class CallbackVerifier
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @param array<string, mixed> $params
     * @param string[] $fields
     */
    public function verify(array $params, array $fields): bool
    {
        if (!isset($params['sign'])) {
            return false;
        }

        $expected = Signer::callbackSign($params, $this->secretKey, $fields);

        return hash_equals($expected, strtoupper((string) $params['sign']));
    }

    /** @param array<string, mixed> $params */
    public function verifyPayment(array $params): bool
    {
        if (isset($params['orderId'])) {
            return $this->verify($params, ['money', 'orderId', 'result_code', 'shopId']);
        }

        return $this->verify($params, ['money', 'order_id', 'result_code', 'shopId']);
    }

    /** @param array<string, mixed> $params */
    public function verifyRefund(array $params): bool
    {
        return $this->verify($params, ['money', 'order_id', 'result_code', 'shop_id']);
    }

    public static function success(): string
    {
        return 'success';
    }

    public static function fail(): string
    {
        return 'fail';
    }
}
