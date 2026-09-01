<?php

declare(strict_types=1);

namespace CongmingPay;

use CongmingPay\Exception\HttpException;
use CongmingPay\Exception\InvalidResponseException;
use CongmingPay\Http\CurlHttpClient;
use CongmingPay\Http\Request;
use CongmingPay\Support\Signer;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
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
        'jsNativePay' => ['order_id', 'money', 'order_type', 'device', 'notify_url'],
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

    /** @var array<string, array<int, string[]>> */
    private const REQUIRED_ONE_OF = [
        'query' => [['order_id', 'out_trade_no', 'transaction_id']],
        'refund' => [['order_id', 'shop_order_id']],
        'queryRefundOrder' => [['refund_order_id', 'plat_refund_order_id']],
        'cancelOrder' => [['order_id', 'out_trade_no']],
        'userCancelOrder' => [['order_id', 'out_trade_no']],
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

    /**
     * 公众号支付（非原生），对应文档 2-1：POST /api/buyPay.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function buyPay(array $params): ApiResponse
    {
        return $this->call('buyPay', $params);
    }

    /**
     * 统一下单支付，对应文档 2-2：POST /api/jsNativePay.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function jsNativePay(array $params): ApiResponse
    {
        return $this->call('jsNativePay', $params);
    }

    /**
     * 条码支付，对应文档 2-3：POST /api/microPay.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function microPay(array $params): ApiResponse
    {
        return $this->call('microPay', $params);
    }

    /**
     * 预支付/主扫支付，对应文档 2-4：POST /api/v3/vprePay.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function prePay(array $params): ApiResponse
    {
        return $this->call('prePay', $params);
    }

    /**
     * 小程序支付，对应文档 2-5：POST /api/miniAppPay.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function miniAppPay(array $params): ApiResponse
    {
        return $this->call('miniAppPay', $params);
    }

    /**
     * 支付查询，对应文档 2-6：POST /api/query.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function query(array $params = []): ApiResponse
    {
        return $this->call('query', $params);
    }

    /**
     * 退款，对应文档 2-7：POST /api/refund.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function refund(array $params = []): ApiResponse
    {
        return $this->call('refund', $params);
    }

    /**
     * 退款查询，对应文档 2-8：POST /api/queryRefundOrder.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function queryRefundOrder(array $params = []): ApiResponse
    {
        return $this->call('queryRefundOrder', $params);
    }

    /**
     * 撤销或关闭订单，对应文档 2-9：POST /api/cancelOrder.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function cancelOrder(array $params = []): ApiResponse
    {
        return $this->call('cancelOrder', $params);
    }

    /**
     * 延迟分账/预授权完成，对应文档 2-11：POST /api/profitorder.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function profitOrder(array $params): ApiResponse
    {
        return $this->call('profitOrder', $params);
    }

    /**
     * 分账退回，对应文档 2-12：POST /api/profitorderback.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function profitOrderBack(array $params): ApiResponse
    {
        return $this->call('profitOrderBack', $params);
    }

    /**
     * 查询微信配置 appid 及授权目录，对应文档 2-14：POST /api/searchMerchantWxAppMsg.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function searchMerchantWxAppMsg(array $params = []): ApiResponse
    {
        return $this->call('searchMerchantWxAppMsg', $params);
    }

    /**
     * 配置微信 appid 及授权目录，对应文档 2-15：POST /api/setMerchantWxAppMsg.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function setMerchantWxAppMsg(array $params): ApiResponse
    {
        return $this->call('setMerchantWxAppMsg', $params);
    }

    /**
     * 授权码获取用户 openid，对应文档 2-16：POST /api/getOpenidByAuthCode.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function getOpenidByAuthCode(array $params): ApiResponse
    {
        return $this->call('getOpenidByAuthCode', $params);
    }

    /**
     * 用户取消支付，对应文档 2-17：POST /api/userCancelOrder.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function userCancelOrder(array $params): ApiResponse
    {
        return $this->call('userCancelOrder', $params);
    }

    /**
     * 授权银联用户标识，对应文档 2-18：POST /api/getUnionOpenid.do。
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function getUnionOpenid(array $params): ApiResponse
    {
        return $this->call('getUnionOpenid', $params);
    }

    /**
     * Use this for document endpoints that are not wrapped yet.
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when the request URI or headers are invalid.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    public function request(string $path, array $params = []): ApiResponse
    {
        return $this->sendJson($path, $this->signedPayload($params));
    }

    /**
     * Merge defaults, inject shop_id/program_id, and attach the request signature.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
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

    /**
     * Sign the payload, then enforce required fields and endpoint-specific rules.
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when the API method name is unknown or a required parameter is missing.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
    private function call(string $name, array $params): ApiResponse
    {
        if (!isset(self::ENDPOINTS[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown API method "%s".', $name));
        }

        $payload = $this->signedPayload($params, $name);
        $this->assertRequired($payload, self::REQUIRED[$name] ?? []);
        $this->assertRequiredOneOf($payload, self::REQUIRED_ONE_OF[$name] ?? []);
        $this->assertEndpointRules($name, $payload);

        $this->logger->info('Calling CongmingPay API.', [
            'api' => $name,
            'endpoint' => self::ENDPOINTS[$name],
        ]);

        return $this->sendJson(self::ENDPOINTS[$name], $payload);
    }

    /**
     * Encode and send a signed JSON request, then wrap the PSR-7 response.
     *
     * @param array<string, mixed> $payload
     * @throws InvalidArgumentException when the request URI or headers are invalid.
     * @throws InvalidResponseException when the request payload cannot be JSON-encoded.
     * @throws HttpException when cURL or the HTTP status indicates a transport failure.
     * @throws ClientExceptionInterface when the injected PSR-18 client rejects the request.
     */
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
            throw new HttpException(sprintf('Unexpected HTTP status code %d: %s', $response->getStatusCode(), $response->getBody()->__toString()));
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
     * @throws InvalidArgumentException when a required parameter is missing.
     */
    private function assertRequired(array $params, array $required): void
    {
        foreach ($required as $field) {
            if (!array_key_exists($field, $params) || $params[$field] === null || $params[$field] === '') {
                throw new InvalidArgumentException(sprintf('Missing required parameter "%s".', $field));
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int, string[]> $groups
     * @throws InvalidArgumentException when none of the alternative required parameters is present.
     */
    private function assertRequiredOneOf(array $params, array $groups): void
    {
        foreach ($groups as $group) {
            foreach ($group as $field) {
                if (array_key_exists($field, $params) && $params[$field] !== null && $params[$field] !== '') {
                    continue 2;
                }
            }

            throw new InvalidArgumentException(sprintf(
                'One of these parameters is required: "%s".',
                implode('", "', $group)
            ));
        }
    }

    /**
     * Endpoint-specific conditional checks after the common required-field rules.
     *
     * @param array<string, mixed> $params
     * @throws InvalidArgumentException when an endpoint-specific required parameter is missing.
     */
    private function assertEndpointRules(string $name, array $params): void
    {
        if ($name === 'jsNativePay') {
            $isDouyinApp = ($params['order_type'] ?? null) === 'douyin'
                && ($params['pay_type'] ?? null) === 'app';

            if (!$isDouyinApp) {
                $this->assertRequired($params, ['openid']);
            }

            return;
        }

        // is_profit 1 (string or int) with empty auth_money requires profit_rule.
        if ($name === 'profitOrder') {
            $isProfit = $params['is_profit'] ?? null;
            $authMoneyEmpty = !array_key_exists('auth_money', $params)
                || $params['auth_money'] === null
                || $params['auth_money'] === '';

            if (($isProfit === 1 || $isProfit === '1') && $authMoneyEmpty) {
                $this->assertRequired($params, ['profit_rule']);
            }

            return;
        }

        // config_type=appid requires appid; path requires auth_list; other values (e.g. sub) pass through.
        if ($name === 'setMerchantWxAppMsg') {
            $configType = $params['config_type'] ?? null;
            if ($configType === 'appid') {
                $this->assertRequired($params, ['appid']);
            } elseif ($configType === 'path') {
                $this->assertRequired($params, ['auth_list']);
            }
        }
    }
}
