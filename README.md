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

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CongmingPay\Config;
use CongmingPay\CongmingPayClient;

$config = new Config(
    'https://your-congmingpay-domain.com',
    'your_program_id',
    'your_shop_id',
    'your_secret_key'
);

$client = new CongmingPayClient($config);

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

The client automatically adds `program_id`, `shop_id`, and `sign`. Request signing follows the document rule: sort request keys, join as `key=value`, append `key=secret`, then uppercase MD5.

Responses implement PSR-7 `Psr\Http\Message\ResponseInterface`.
Requests use PSR-7 `Psr\Http\Message\RequestInterface`, and the bundled cURL client implements PSR-18 `Psr\Http\Client\ClientInterface`.

```php
$statusCode = $response->getStatusCode();
$contentType = $response->getHeaderLine('content-type');
$rawBody = (string) $response->getBody(); // PSR-7 stream
$rawBody = $response->getRawBody();       // convenience alias
$data = $response->toArray();             // decoded JSON payload
```

```php
use CongmingPay\Http\Request;

$psrRequest = new Request('POST', 'https://merchant.example.com/api', [
    'Content-Type' => 'application/json',
], '{"foo":"bar"}');

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
