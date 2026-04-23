# CongmingPay PHP SDK

PHP 7.4+ SDK for CongmingPay payment APIs.

## Install

```bash
composer require congmingpay/php-sdk
```

For local development in this repository:

```bash
composer install
```

## Usage

For the logging example below:

```bash
composer require monolog/monolog
```

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CongmingPay\Config;
use CongmingPay\CongmingPayClient;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$config = new Config(
    'https://your-congmingpay-domain.com',
    null, // optional program_id; pass your program id here when your merchant requires it
    'your_shop_id',
    'your_secret_key',
    30,
    true,
    [
        'notify_url' => 'https://merchant.example.com/default-notify',
        'ver' => '3.0',
    ],
    [
        'buyPay' => [
            'device' => 'POS-01',
            'order_type' => 'weixin',
        ],
    ]
);

$logger = new Logger('congmingpay');
$logger->pushHandler(new StreamHandler(__DIR__ . '/congmingpay.log'));

$client = new CongmingPayClient($config, null, $logger);

$response = $client->buyPay([
    'money' => '12.35',
    'order_id' => '202604231200000001',
    'is_notify_new' => '1',
    'goods_msg' => 'Order 202604231200000001',
]);

$data = $response->toArray();
```

The client automatically adds `shop_id` and `sign`. If `program_id` is configured, it is also added.
Parameters are merged in this order: system defaults -> global defaults -> endpoint defaults -> per-request params. Per-request params override all defaults.
Current system defaults:

- `buyPay`: `ver=3.0`, `profit_share_type=0`, `is_notify_new=0`
- `jsNativePay`: `profit_share_type=0`, `is_notify_new=0`
- `microPay`: `profit_share_type=0`, `is_notify_new=0`
- `prePay`: `version=3.0`, `profit_share_type=0`
- `miniAppPay`: `ver=3.0`, `profit_share_type=0`, `is_notify_new=0`

Request signing follows the document rule: sort request keys, join as `key=value`, append `key=secret`, then uppercase MD5.
If no logger is provided, the SDK uses PSR-3 `NullLogger`.

The SDK uses these PSR contracts:

- PSR-4 autoloading
- PSR-7 requests, responses, streams, and URIs
- PSR-18 HTTP client transport
- PSR-3 logging

API methods return `CongmingPay\ApiResponse`, which wraps the raw PSR-7 response and decoded JSON data.

```php
$psrResponse = $response->getResponse();
$statusCode = $psrResponse->getStatusCode();
$contentType = $psrResponse->getHeaderLine('content-type');
$rawBody = (string) $psrResponse->getBody();
$data = $response->toArray();
```

```php
use CongmingPay\Http\Request;
use Psr\Http\Client\ClientInterface;

$psrRequest = new Request('POST', 'https://merchant.example.com/api', [
    'Content-Type' => 'application/json',
], '{"foo":"bar"}');

/** @var ClientInterface $httpClient */
$psrResponse = $httpClient->sendRequest($psrRequest);
```

## Wrapped APIs

| Method | Endpoint | 中文说明（飞书文档章节） |
| --- | --- | --- |
| `buyPay()` | `/api/buyPay.do` | 公众号支付接口（非原生，2-1） |
| `jsNativePay()` | `/api/jsNativePay.do` | 统一下单接口（2-2） |
| `microPay()` | `/api/microPay.do` | 条码支付接口（2-3） |
| `prePay()` | `/api/v3/vprePay.do` | 预支付接口（2-4） |
| `miniAppPay()` | `/api/miniAppPay.do` | 小程序支付接口（2-5） |
| `query()` | `/api/query.do` | 支付查询接口（2-6） |
| `refund()` | `/api/refund.do` | 退款接口（2-7） |
| `queryRefundOrder()` | `/api/queryRefundOrder.do` | 退款查询接口（2-8） |
| `cancelOrder()` | `/api/cancelOrder.do` | 撤销或关闭订单接口（2-9） |
| `profitOrder()` | `/api/profitorder.do` | 延迟分账及预授权完成接口（2-11） |
| `profitOrderBack()` | `/api/profitorderback.do` | 分账退回接口（2-12） |
| `searchMerchantWxAppMsg()` | `/api/searchMerchantWxAppMsg.do` | 查询用户微信配置 appid 及授权目录（2-14） |
| `setMerchantWxAppMsg()` | `/api/setMerchantWxAppMsg.do` | 用户配置微信 appid 及授权目录（2-15） |
| `getOpenidByAuthCode()` | `/api/getOpenidByAuthCode.do` | 授权码获取用户 openid（2-16） |
| `userCancelOrder()` | `/api/userCancelOrder.do` | 用户取消支付接口（2-17） |
| `getUnionOpenid()` | `/api/getUnionOpenid.do` | 授权银联用户标识接口（2-18） |

For an unwrapped endpoint:

```php
$response = $client->request('/api/custom.do', ['foo' => 'bar']);
```

## Callback Verification

```php
use CongmingPay\CallbackVerifier;

$verifier = new CallbackVerifier('your_secret_key');

$payload = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? json_decode(file_get_contents('php://input'), true)
    : $_GET;

if (!$verifier->verifyPayment($payload ?: [])) {
    http_response_code(400);
    echo CallbackVerifier::fail();
    return;
}

echo CallbackVerifier::success();
```

Refund callback verification uses:

```php
$ok = $verifier->verifyRefund($payload);
```

Use `verify($payload, ['field_a', 'field_b'])` if a custom callback field list is needed.
