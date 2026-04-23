<?php

declare(strict_types=1);

namespace CongmingPay;

use CongmingPay\Http\CurlHttpClient;
use CongmingPay\Http\Request;
use CongmingPay\Exception\HttpException;
use CongmingPay\Exception\InvalidResponseException;
use CongmingPay\Support\Signer;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CongmingPayClient
{
    /** @var array<string, mixed> */
    private const SYSTEM_DEFAULTS = [];

    /** @var array<string, array<string, mixed>> */
    private const SYSTEM_ENDPOINT_DEFAULTS = [
        'buyPay' => [
            'ver' => '3.0',
            'profit_share_type' => '0',
            'is_notify_new' => '0',
        ],
        'jsNativePay' => [
            'profit_share_type' => '0',
            'is_notify_new' => '0',
        ],
        'microPay' => [
            'profit_share_type' => '0',
            'is_notify_new' => '0',
        ],
        'prePay' => [
            'version' => '3.0',
            'profit_share_type' => '0',
        ],
        'miniAppPay' => [
            'ver' => '3.0',
            'profit_share_type' => '0',
            'is_notify_new' => '0',
        ],
    ];

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

    private Config $config;

    private ClientInterface $httpClient;

    private LoggerInterface $logger;

    public function __construct(Config $config, ?ClientInterface $httpClient = null, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient ?? new CurlHttpClient($config, $this->logger);
    }

    /** @param array<string, mixed> $params */
    public function buyPay(array $params): ApiResponse
    {
        return $this->call('buyPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function jsNativePay(array $params): ApiResponse
    {
        return $this->call('jsNativePay', $params);
    }

    /** @param array<string, mixed> $params */
    public function microPay(array $params): ApiResponse
    {
        return $this->call('microPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function prePay(array $params): ApiResponse
    {
        return $this->call('prePay', $params);
    }

    /** @param array<string, mixed> $params */
    public function miniAppPay(array $params): ApiResponse
    {
        return $this->call('miniAppPay', $params);
    }

    /** @param array<string, mixed> $params */
    public function query(array $params = []): ApiResponse
    {
        return $this->call('query', $params);
    }

    /** @param array<string, mixed> $params */
    public function refund(array $params = []): ApiResponse
    {
        return $this->call('refund', $params);
    }

    /** @param array<string, mixed> $params */
    public function queryRefundOrder(array $params = []): ApiResponse
    {
        return $this->call('queryRefundOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function cancelOrder(array $params = []): ApiResponse
    {
        return $this->call('cancelOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function profitOrder(array $params): ApiResponse
    {
        return $this->call('profitOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function profitOrderBack(array $params): ApiResponse
    {
        return $this->call('profitOrderBack', $params);
    }

    /** @param array<string, mixed> $params */
    public function searchMerchantWxAppMsg(array $params = []): ApiResponse
    {
        return $this->call('searchMerchantWxAppMsg', $params);
    }

    /** @param array<string, mixed> $params */
    public function setMerchantWxAppMsg(array $params): ApiResponse
    {
        return $this->call('setMerchantWxAppMsg', $params);
    }

    /** @param array<string, mixed> $params */
    public function getOpenidByAuthCode(array $params): ApiResponse
    {
        return $this->call('getOpenidByAuthCode', $params);
    }

    /** @param array<string, mixed> $params */
    public function userCancelOrder(array $params): ApiResponse
    {
        return $this->call('userCancelOrder', $params);
    }

    /** @param array<string, mixed> $params */
    public function getUnionOpenid(array $params): ApiResponse
    {
        return $this->call('getUnionOpenid', $params);
    }

    /**
     * Use this for document endpoints that are not wrapped yet.
     *
     * @param array<string, mixed> $params
     */
    public function request(string $path, array $params = []): ApiResponse
    {
        return $this->sendJson($path, $this->signedPayload($params));
    }

    /** @param array<string, mixed> $params */
    public function signedPayload(array $params, ?string $endpointKey = null): array
    {
        $payload = array_merge(
            self::SYSTEM_DEFAULTS,
            $endpointKey === null ? [] : $this->getSystemEndpointDefaults($endpointKey),
            $this->config->getDefaultParams(),
            $endpointKey === null ? [] : $this->config->getEndpointDefaults($endpointKey),
            $params
        );
        $payload = $this->removeNulls($payload);
        $payload['shop_id'] = $this->config->getShopId();

        if ($this->config->getProgramId() !== null && !array_key_exists('program_id', $payload)) {
            $payload['program_id'] = $this->config->getProgramId();
        }

        $payload['sign'] = Signer::sign($payload, $this->config->getSecretKey());

        return $payload;
    }

    /** @param array<string, mixed> $params */
    private function call(string $name, array $params): ApiResponse
    {
        if (!isset(self::ENDPOINTS[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown API method "%s".', $name));
        }

        $payload = $this->signedPayload($params, $name);
        $this->assertRequired($payload, self::REQUIRED[$name] ?? []);

        $this->logger->info('Calling CongmingPay API.', [
            'api' => $name,
            'endpoint' => self::ENDPOINTS[$name],
        ]);

        return $this->sendJson(self::ENDPOINTS[$name], $payload);
    }

    /** @param array<string, mixed> $params */
    private function sendJson(string $path, array $payload): ApiResponse
    {
        $url = $this->config->getBaseUri() . '/' . ltrim($path, '/');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new InvalidResponseException('Failed to encode request payload: ' . json_last_error_msg());
        }

        $request = new Request('POST', $url, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $body);

        $response = $this->httpClient->sendRequest($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $this->logger->warning('CongmingPay API returned non-success HTTP status.', [
                'endpoint' => $path,
                'status_code' => $response->getStatusCode(),
            ]);
            throw new HttpException(sprintf('Unexpected HTTP status code %d: %s', $response->getStatusCode(), (string) $response->getBody()));
        }

        return new ApiResponse($response, $this->decodeJson($response));
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(ResponseInterface $response): ?array
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('CongmingPay API response is not valid JSON.', [
                'json_error' => json_last_error_msg(),
            ]);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, mixed> */
    private function getSystemEndpointDefaults(string $endpointKey): array
    {
        if (!isset(self::SYSTEM_ENDPOINT_DEFAULTS[$endpointKey])) {
            return [];
        }

        return self::SYSTEM_ENDPOINT_DEFAULTS[$endpointKey];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function removeNulls(array $params): array
    {
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            }
        }

        return $params;
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
