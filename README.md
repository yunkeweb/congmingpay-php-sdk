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
    'your_secret_key'
);

$logger = new Logger('congmingpay');
$logger->pushHandler(new StreamHandler(__DIR__ . '/congmingpay.log'));

$client = new CongmingPayClient($config, null, $logger);

$response = $client->buyPay([
    'money' => '12.35',
    'order_id' => '202604231200000001',
    'order_type' => 'weixin',
    'device' => '100101',
    'notify_url' => 'https://merchant.example.com/payment/notify',
    'is_notify_new' => '1',
    'ver' => '3.0',
    'goods_msg' => 'Order 202604231200000001',
]);

$data = $response->toArray();
```

The client automatically adds `shop_id` and `sign`. If `program_id` is configured, it is also added. Request signing follows the document rule: sort request keys, join as `key=value`, append `key=secret`, then uppercase MD5.
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

| Method | Endpoint |
| --- | --- |
| `buyPay()` | `/api/buyPay.do` |
| `jsNativePay()` | `/api/jsNativePay.do` |
| `microPay()` | `/api/microPay.do` |
| `prePay()` | `/api/v3/vprePay.do` |
| `miniAppPay()` | `/api/miniAppPay.do` |
| `query()` | `/api/query.do` |
| `refund()` | `/api/refund.do` |
| `queryRefundOrder()` | `/api/queryRefundOrder.do` |
| `cancelOrder()` | `/api/cancelOrder.do` |
| `profitOrder()` | `/api/profitorder.do` |
| `profitOrderBack()` | `/api/profitorderback.do` |
| `searchMerchantWxAppMsg()` | `/api/searchMerchantWxAppMsg.do` |
| `setMerchantWxAppMsg()` | `/api/setMerchantWxAppMsg.do` |
| `getOpenidByAuthCode()` | `/api/getOpenidByAuthCode.do` |
| `userCancelOrder()` | `/api/userCancelOrder.do` |
| `getUnionOpenid()` | `/api/getUnionOpenid.do` |

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
