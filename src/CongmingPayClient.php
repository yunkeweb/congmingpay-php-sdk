<?php

declare(strict_types=1);

namespace CongmingPay;

use CongmingPay\Http\CurlHttpClient;
use CongmingPay\Http\HttpClientInterface;
use CongmingPay\Http\Response;
use CongmingPay\Support\Signer;
use InvalidArgumentException;

final class CongmingPayClient
{
    private const ENDPOINTS = [
        'buyPay' => '/api/buyPay.do',
        'jsNativePay' => '/api/jsNativePay.do',
        'microPay' => '/api/microPay.do',
        'prePay' => '/api/v3/vprePay.do',
        'miniAppPay' => '/api/miniAppPay.do',
        'query' => '/api/query.do',
        'refund' => '/api/refund.do',
        'queryRefundOrder' => '/api/queryRefundOrder.do',
        'cancelOrder' => '/api/cancelOrder.do',
        'profitOrder' => '/api/profitorder.do',
        'profitOrderBack' => '/api/profitorderback.do',
        'searchMerchantWxAppMsg' => '/api/searchMerchantWxAppMsg.do',
        'setMerchantWxAppMsg' => '/api/setMerchantWxAppMsg.do',
        'getOpenidByAuthCode' => '/api/getOpenidByAuthCode.do',
        'userCancelOrder' => '/api/userCancelOrder.do',
        'getUnionOpenid' => '/api/getUnionOpenid.do',
    ];

    private const REQUIRED = [
        'buyPay' => ['money', 'order_id', 'order_type', 'device', 'notify_url', 'ver'],
        'jsNativePay' => ['order_id', 'money', 'order_type', 'device', 'openid', 'notify_url'],
        'microPay' => ['money', 'order_id', 'auth_code', 'device'],
        'prePay' => ['money', 'order_id', 'version', 'notify_url'],
        'miniAppPay' => ['money', 'order_id', 'order_type', 'device', 'appid', 'openid', 'notify_url', 'ver'],
        'query' => [],
        'refund' => [],
        'queryRefundOrder' => [],
        'cancelOrder' => [],
        'profitOrder' => ['order_id', 'out_trade_no', 'is_profit'],
        'profitOrderBack' => ['order_id', 'profit_order_id', 'ps_shop_id', 'ps_order_back_money', 'profit_back_notify_url'],
        'searchMerchantWxAppMsg' => [],
        'setMerchantWxAppMsg' => ['config_type'],
        'getOpenidByAuthCode' => ['auth_code'],
        'userCancelOrder' => ['error_msg'],
        'getUnionOpenid' => ['code', 'payment_app'],
    ];

    /** @var Config */
    private $config;

    /** @var HttpClientInterface */
    private $httpClient;

    public function __construct(Config $config, ?HttpClientInterface $httpClient = null)
    {
        $this->config = $config;
        $this->httpClient = $httpClient ?? new CurlHttpClient($config);
    }

    /** @param array<string, mixed> $params */
    public function buyPay(array $params): Response
    {
        return $this->call('buyPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function jsNativePay(array $params): Response
    {
        return $this->call('jsNativePay', $params);
    }

    /** @param array<string, mixed> $params */
    public function microPay(array $params): Response
    {
        return $this->call('microPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function prePay(array $params): Response
    {
        return $this->call('prePay', $params);
    }

    /** @param array<string, mixed> $params */
    public function miniAppPay(array $params): Response
    {
        return $this->call('miniAppPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function query(array $params = []): Response
    {
        return $this->call('query', $params);
    }

    /** @param array<string, mixed> $params */
    public function refund(array $params = []): Response
    {
        return $this->call('refund', $params);
    }

    /** @param array<string, mixed> $params */
    public function queryRefundOrder(array $params = []): Response
    {
        return $this->call('queryRefundOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function cancelOrder(array $params = []): Response
    {
        return $this->call('cancelOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function profitOrder(array $params): Response
    {
        return $this->call('profitOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function profitOrderBack(array $params): Response
    {
        return $this->call('profitOrderBack', $params);
    }

    /** @param array<string, mixed> $params */
    public function searchMerchantWxAppMsg(array $params = []): Response
    {
        return $this->call('searchMerchantWxAppMsg', $params);
    }

    /** @param array<string, mixed> $params */
    public function setMerchantWxAppMsg(array $params): Response
    {
        return $this->call('setMerchantWxAppMsg', $params);
    }

    /** @param array<string, mixed> $params */
    public function getOpenidByAuthCode(array $params): Response
    {
        return $this->call('getOpenidByAuthCode', $params);
    }

    /** @param array<string, mixed> $params */
    public function userCancelOrder(array $params): Response
    {
        return $this->call('userCancelOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function getUnionOpenid(array $params): Response
    {
        return $this->call('getUnionOpenid', $params);
    }

    /**
     * Use this for document endpoints that are not wrapped yet.
     *
     * @param array<string, mixed> $params
     */
    public function request(string $path, array $params = []): Response
    {
        return $this->send($path, $params);
    }

    /** @param array<string, mixed> $params */
    public function signedPayload(array $params): array
    {
        $payload = array_merge([
            'program_id' => $this->config->getProgramId(),
            'shop_id' => $this->config->getShopId(),
        ], $params);
        $payload['sign'] = Signer::sign($payload, $this->config->getSecretKey());

        return $payload;
    }

    /** @param array<string, mixed> $params */
    private function call(string $name, array $params): Response
    {
        if (!isset(self::ENDPOINTS[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown API method "%s".', $name));
        }

        $this->assertRequired($params, self::REQUIRED[$name] ?? []);

        return $this->send(self::ENDPOINTS[$name], $params);
    }

    /** @param array<string, mixed> $params */
    private function send(string $path, array $params): Response
    {
        $url = $this->config->getBaseUri() . '/' . ltrim($path, '/');

        return $this->httpClient->postJson($url, $this->signedPayload($params));
    }

    /**
     * @param array<string, mixed> $params
     * @param string[] $required
     */
    private function assertRequired(array $params, array $required): void
    {
        foreach ($required as $field) {
            if (!array_key_exists($field, $params) || $params[$field] === null || $params[$field] === '') {
                throw new InvalidArgumentException(sprintf('Missing required parameter "%s".', $field));
            }
        }
    }
}
