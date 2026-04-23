<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CongmingPay\CallbackVerifier;
use CongmingPay\Config;
use CongmingPay\CongmingPayClient;
use CongmingPay\Http\Request;
use CongmingPay\Http\Response;
use CongmingPay\Support\Signer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

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

expectTrue($http->request instanceof RequestInterface, 'PSR request was not sent.');
$payload = json_decode((string) $http->request->getBody(), true);
expectTrue(is_array($payload), 'Request payload is not JSON.');
expectTrue($payload['program_id'] === 'pid', 'program_id was not injected.');
expectTrue($payload['shop_id'] === 'sid', 'shop_id was not injected.');
expectTrue(isset($payload['sign']), 'sign was not injected.');
expectTrue($apiResponse->isSuccessful() === true, 'API response should be successful.');
expectTrue($apiResponse->getResponse() instanceof ResponseInterface, 'API response does not expose PSR response.');
expectTrue(count($logger->records) > 0, 'Logger did not receive SDK records.');

$response = new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
expectTrue($response instanceof ResponseInterface, 'Response does not implement PSR-7 ResponseInterface.');
expectTrue($response->getHeaderLine('content-type') === 'application/json', 'Header lookup is not PSR-7 compatible.');
expectTrue((string) $response->getBody() === '{"result_code":"success"}', 'Body stream does not expose response body.');
expectTrue($response->withAddedHeader('X-Test', 'a')->withAddedHeader('X-Test', 'b')->getHeaderLine('x-test') === 'a, b', 'Header line formatting is not PSR compatible.');

$request = new Request('POST', 'https://pay.example.com/api/query.do', ['Content-Type' => 'application/json'], '{"foo":"bar"}');
expectTrue($request instanceof RequestInterface, 'Request does not implement PSR-7 RequestInterface.');
expectTrue($http instanceof ClientInterface, 'HTTP client does not implement PSR-18 ClientInterface.');
expectTrue($request->getMethod() === 'POST', 'Request method mismatch.');
expectTrue($request->getHeaderLine('content-type') === 'application/json', 'Request header lookup is not PSR-7 compatible.');
expectTrue($request->getHeaderLine('host') === 'pay.example.com', 'Request Host header was not derived from constructor URI.');
expectTrue($request->withUri(new CongmingPay\Http\Uri('https://other.example.com/path'))->getHeaderLine('host') === 'other.example.com', 'Request Host header was not updated from URI.');

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
$payloadWithoutProgramId = json_decode((string) $httpWithoutProgramId->request->getBody(), true);
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
$payloadWithDefaults = json_decode((string) $httpDefaults->request->getBody(), true);
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
$payloadOverrideDefaults = json_decode((string) $httpDefaults->request->getBody(), true);
expectTrue(is_array($payloadOverrideDefaults), 'Request payload with overrides is not JSON.');
expectTrue($payloadOverrideDefaults['notify_url'] === 'https://merchant.example.com/override-notify', 'Per-request notify_url should override defaults.');
expectTrue($payloadOverrideDefaults['device'] === 'OVERRIDE_DEVICE', 'Per-request device should override defaults.');
expectTrue($payloadOverrideDefaults['is_notify_new'] === '0', 'System default is_notify_new should remain when not overridden.');

$clientWithDefaults->prePay([
    'money' => '3.00',
    'order_id' => 'OID_PREPAY',
    'notify_url' => 'https://merchant.example.com/prepay-notify',
]);
$payloadPrePay = json_decode((string) $httpDefaults->request->getBody(), true);
expectTrue(is_array($payloadPrePay), 'Prepay payload is not JSON.');
expectTrue($payloadPrePay['version'] === '3.0', 'System default version for prePay was not applied.');
expectTrue($payloadPrePay['profit_share_type'] === '0', 'System default profit_share_type for prePay was not applied.');

echo "OK\n";
