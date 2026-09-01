<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CongmingPay\CallbackVerifier;
use CongmingPay\Config;
use CongmingPay\CongmingPayClient;
use CongmingPay\Http\Request;
use CongmingPay\Http\Response;
use CongmingPay\Http\Uri;
use CongmingPay\Support\Signer;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

/**
 * @throws RuntimeException
 */
function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function expectThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        return;
    }

    throw new RuntimeException($message);
}

/**
 * @throws RuntimeException
 */
function sentRequest(?RequestInterface $request): RequestInterface
{
    if (!$request instanceof RequestInterface) {
        throw new RuntimeException('PSR request was not sent.');
    }

    return $request;
}

try {
$requestSign = Signer::sign([
    'country' => '中国',
    'sex' => '男',
    'name' => '张三',
    'age' => '18',
], '123456789');

expectTrue($requestSign === strtoupper(md5('age=18&country=中国&name=张三&sex=男&key=123456789')), 'Request sign mismatch.');

$callbackPayload = [
    'money' => '50.0',
    'orderId' => 'CZ2021111117221351790',
    'result_code' => 'SUCCESS',
    'shopId' => '93fe1c13cb668954331a6e34115d53c0',
];
$callbackPayload['sign'] = strtoupper(md5('money=50.0&orderId=CZ2021111117221351790&result_code=SUCCESS&shopId=93fe1c13cb668954331a6e34115d53c0&key=07DEA4C6AD8A23C3A416B9FD66DCC8A9'));

$verifier = new CallbackVerifier('07DEA4C6AD8A23C3A416B9FD66DCC8A9');
expectTrue($verifier->verifyPayment($callbackPayload) === true, 'Callback verification failed.');

$logger = new class extends AbstractLogger {
    /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
};

$http = new class implements ClientInterface {
    public ?RequestInterface $request = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
    }
};

$client = new CongmingPayClient(new Config('https://pay.example.com', 'pid', 'sid', 'secret'), $http, $logger);
$apiResponse = $client->query(['order_id' => 'OID']);

$payload = json_decode((string) sentRequest($http->request)->getBody(), true);
expectTrue(is_array($payload), 'Request payload is not JSON.');
expectTrue($payload['program_id'] === 'pid', 'program_id was not injected.');
expectTrue($payload['shop_id'] === 'sid', 'shop_id was not injected.');
expectTrue(isset($payload['sign']), 'sign was not injected.');
expectTrue($apiResponse->isSuccessful() === true, 'API response should be successful.');
expectTrue($apiResponse->getResponse()->getStatusCode() === 200, 'API response does not expose PSR response.');
expectTrue(count($logger->records) > 0, 'Logger did not receive SDK records.');

$response = new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
expectTrue($response->getHeaderLine('content-type') === 'application/json', 'Header lookup is not PSR-7 compatible.');
expectTrue((string) $response->getBody() === '{"result_code":"success"}', 'Body stream does not expose response body.');
expectTrue($response->withAddedHeader('X-Test', 'a')->withAddedHeader('X-Test', 'b')->getHeaderLine('x-test') === 'a, b', 'Header line formatting is not PSR compatible.');
expectThrows(static function (): void {
    new Response(99, [], '');
}, 'Invalid response status code should fail.');
expectThrows(static function () use ($response): void {
    $response->withAddedHeader('Bad Header', 'value');
}, 'Invalid response header name should fail.');

$request = new Request('POST', 'https://pay.example.com/api/query.do', ['Content-Type' => 'application/json'], '{"foo":"bar"}');
expectTrue($request->getMethod() === 'POST', 'Request method mismatch.');
expectTrue($request->getHeaderLine('content-type') === 'application/json', 'Request header lookup is not PSR-7 compatible.');
expectTrue($request->getHeaderLine('host') === 'pay.example.com', 'Request Host header was not derived from constructor URI.');
expectTrue($request->withUri(new Uri('https://other.example.com/path'))->getHeaderLine('host') === 'other.example.com', 'Request Host header was not updated from URI.');
expectTrue((new Request('get', 'https://pay.example.com/api'))->getMethod() === 'get', 'Request method casing should be preserved.');
expectTrue((new Request('POST', 'https://pay.example.com:8443/api'))->getHeaderLine('host') === 'pay.example.com:8443', 'Request Host header should include non-default port.');
expectTrue($request->withUri(new Uri('https://other.example.com:8443/path'))->getHeaderLine('host') === 'other.example.com:8443', 'Request Host header from withUri should include non-default port.');
expectThrows(static function () use ($request): void {
    $request->withAddedHeader('Bad Header', 'value');
}, 'Invalid request header name should fail.');

$httpWithoutProgramId = new class implements ClientInterface {
    public ?RequestInterface $request = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
    }
};
$clientWithoutProgramId = new CongmingPayClient(new Config('https://pay.example.com', null, 'sid', 'secret'), $httpWithoutProgramId);
$clientWithoutProgramId->query(['order_id' => 'OID']);
$payloadWithoutProgramId = json_decode((string) sentRequest($httpWithoutProgramId->request)->getBody(), true);
expectTrue(is_array($payloadWithoutProgramId), 'Request payload without program_id is not JSON.');
expectTrue(!array_key_exists('program_id', $payloadWithoutProgramId), 'program_id should not be injected when omitted.');

$httpDefaults = new class implements ClientInterface {
    public ?RequestInterface $request = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
    }
};
$configWithDefaults = new Config(
    'https://pay.example.com',
    null,
    'sid',
    'secret',
    30,
    true,
    [
        'notify_url' => 'https://merchant.example.com/default-notify',
    ],
    [
        'buyPay' => [
            'device' => 'DEFAULT_DEVICE',
            'order_type' => 'weixin',
        ],
    ]
);
$clientWithDefaults = new CongmingPayClient($configWithDefaults, $httpDefaults);
$clientWithDefaults->buyPay([
    'money' => '1.00',
    'order_id' => 'OID_DEFAULT',
]);
$payloadWithDefaults = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadWithDefaults), 'Request payload with defaults is not JSON.');
expectTrue($payloadWithDefaults['notify_url'] === 'https://merchant.example.com/default-notify', 'Default notify_url was not applied.');
expectTrue($payloadWithDefaults['ver'] === '3.0', 'System default ver was not applied.');
expectTrue($payloadWithDefaults['profit_share_type'] === '0', 'System default profit_share_type was not applied.');
expectTrue($payloadWithDefaults['is_notify_new'] === '0', 'System default is_notify_new was not applied.');
expectTrue($payloadWithDefaults['device'] === 'DEFAULT_DEVICE', 'Endpoint default device was not applied.');
expectTrue($payloadWithDefaults['order_type'] === 'weixin', 'Endpoint default order_type was not applied.');

$clientWithDefaults->buyPay([
    'money' => '2.00',
    'order_id' => 'OID_OVERRIDE',
    'notify_url' => 'https://merchant.example.com/override-notify',
    'device' => 'OVERRIDE_DEVICE',
]);
$payloadOverrideDefaults = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadOverrideDefaults), 'Request payload with overrides is not JSON.');
expectTrue($payloadOverrideDefaults['notify_url'] === 'https://merchant.example.com/override-notify', 'Per-request notify_url should override defaults.');
expectTrue($payloadOverrideDefaults['device'] === 'OVERRIDE_DEVICE', 'Per-request device should override defaults.');
expectTrue($payloadOverrideDefaults['is_notify_new'] === '0', 'System default is_notify_new should remain when not overridden.');

$clientWithDefaults->prePay([
    'money' => '3.00',
    'order_id' => 'OID_PREPAY',
    'notify_url' => 'https://merchant.example.com/prepay-notify',
]);
$payloadPrePay = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadPrePay), 'Prepay payload is not JSON.');
expectTrue($payloadPrePay['version'] === '3.0', 'System default version for prePay was not applied.');
expectTrue($payloadPrePay['profit_share_type'] === '0', 'System default profit_share_type for prePay was not applied.');

$clientWithDefaults->jsNativePay([
    'order_id' => 'OID_DOUYIN',
    'money' => '4.00',
    'order_type' => 'douyin',
    'pay_type' => 'app',
    'device' => 'DEVICE_DOUYIN',
    'notify_url' => 'https://merchant.example.com/douyin-notify',
]);
$payloadDouyin = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadDouyin), 'Douyin app payload is not JSON.');
expectTrue(!array_key_exists('openid', $payloadDouyin), 'Douyin app jsNativePay should not require openid.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->jsNativePay([
        'order_id' => 'OID_JSAPI',
        'money' => '4.00',
        'order_type' => 'weixin',
        'device' => 'DEVICE_JSAPI',
        'notify_url' => 'https://merchant.example.com/jsapi-notify',
    ]);
}, 'Non-Douyin jsNativePay should require openid.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->query();
}, 'Query should require order_id, out_trade_no, or transaction_id.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->refund();
}, 'Refund should require order_id or shop_order_id.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->queryRefundOrder();
}, 'Refund query should require refund_order_id or plat_refund_order_id.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->cancelOrder();
}, 'Cancel order should require order_id or out_trade_no.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->userCancelOrder(['error_msg' => 'user canceled']);
}, 'User cancel order should require order_id or out_trade_no.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->profitOrder([
        'order_id' => 'OID_PROFIT',
        'out_trade_no' => 'OUT_PROFIT',
        'is_profit' => '1',
    ]);
}, 'profitOrder should require profit_rule when is_profit is string 1 and auth_money is empty.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->profitOrder([
        'order_id' => 'OID_PROFIT_INT',
        'out_trade_no' => 'OUT_PROFIT_INT',
        'is_profit' => 1,
        'auth_money' => '',
    ]);
}, 'profitOrder should require profit_rule when is_profit is integer 1 and auth_money is empty.');

$clientWithDefaults->profitOrder([
    'order_id' => 'OID_PROFIT_RULE',
    'out_trade_no' => 'OUT_PROFIT_RULE',
    'is_profit' => '1',
    'profit_rule' => 'RULE',
]);
$payloadProfitRule = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadProfitRule), 'profitOrder payload with profit_rule is not JSON.');
expectTrue($payloadProfitRule['profit_rule'] === 'RULE', 'profitOrder profit_rule was not sent.');

$clientWithDefaults->profitOrder([
    'order_id' => 'OID_PROFIT_AUTH',
    'out_trade_no' => 'OUT_PROFIT_AUTH',
    'is_profit' => 1,
    'auth_money' => '10.00',
]);
$payloadProfitAuth = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadProfitAuth), 'profitOrder payload with auth_money is not JSON.');
expectTrue($payloadProfitAuth['auth_money'] === '10.00', 'profitOrder auth_money was not sent.');

$clientWithDefaults->profitOrder([
    'order_id' => 'OID_PROFIT_SKIP',
    'out_trade_no' => 'OUT_PROFIT_SKIP',
    'is_profit' => '0',
]);
$payloadProfitSkip = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadProfitSkip), 'profitOrder payload without profit_rule is not JSON.');
expectTrue(!array_key_exists('profit_rule', $payloadProfitSkip), 'profitOrder should not require profit_rule when is_profit is not 1.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->setMerchantWxAppMsg([
        'config_type' => 'appid',
    ]);
}, 'setMerchantWxAppMsg should require appid when config_type is appid.');

$clientWithDefaults->setMerchantWxAppMsg([
    'config_type' => 'appid',
    'appid' => 'wx123',
]);
$payloadWxAppid = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadWxAppid), 'setMerchantWxAppMsg appid payload is not JSON.');
expectTrue($payloadWxAppid['appid'] === 'wx123', 'setMerchantWxAppMsg appid was not sent.');

expectThrows(static function () use ($clientWithDefaults): void {
    $clientWithDefaults->setMerchantWxAppMsg([
        'config_type' => 'path',
    ]);
}, 'setMerchantWxAppMsg should require auth_list when config_type is path.');

$clientWithDefaults->setMerchantWxAppMsg([
    'config_type' => 'path',
    'auth_list' => 'https://merchant.example.com/',
]);
$payloadWxPath = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadWxPath), 'setMerchantWxAppMsg path payload is not JSON.');
expectTrue($payloadWxPath['auth_list'] === 'https://merchant.example.com/', 'setMerchantWxAppMsg auth_list was not sent.');

$clientWithDefaults->setMerchantWxAppMsg([
    'config_type' => 'sub',
]);
$payloadWxSub = json_decode((string) sentRequest($httpDefaults->request)->getBody(), true);
expectTrue(is_array($payloadWxSub), 'setMerchantWxAppMsg sub payload is not JSON.');
expectTrue($payloadWxSub['config_type'] === 'sub', 'setMerchantWxAppMsg should pass through config_type=sub.');

echo "OK\n";
} catch (ClientExceptionInterface $exception) {
    throw new RuntimeException('CongmingPay HTTP client failed.', $exception->getCode(), $exception);
}
