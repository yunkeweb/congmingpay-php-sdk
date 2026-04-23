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

function expect_true(bool $condition, string $message): void
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

expect_true($requestSign === strtoupper(md5('age=18&country=中国&name=张三&sex=男&key=123456789')), 'Request sign mismatch.');

$callbackPayload = [
    'money' => '50.0',
    'orderId' => 'CZ2021111117221351790',
    'result_code' => 'SUCCESS',
    'shopId' => '93fe1c13cb668954331a6e34115d53c0',
];
$callbackPayload['sign'] = strtoupper(md5('money=50.0&orderId=CZ2021111117221351790&result_code=SUCCESS&shopId=93fe1c13cb668954331a6e34115d53c0&key=07DEA4C6AD8A23C3A416B9FD66DCC8A9'));

$verifier = new CallbackVerifier('07DEA4C6AD8A23C3A416B9FD66DCC8A9');
expect_true($verifier->verifyPayment($callbackPayload) === true, 'Callback verification failed.');

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

expect_true($http->request instanceof RequestInterface, 'PSR request was not sent.');
$payload = json_decode((string) $http->request->getBody(), true);
expect_true(is_array($payload), 'Request payload is not JSON.');
expect_true($payload['program_id'] === 'pid', 'program_id was not injected.');
expect_true($payload['shop_id'] === 'sid', 'shop_id was not injected.');
expect_true(isset($payload['sign']), 'sign was not injected.');
expect_true($apiResponse->isSuccessful() === true, 'API response should be successful.');
expect_true($apiResponse->getResponse() instanceof ResponseInterface, 'API response does not expose PSR response.');
expect_true(count($logger->records) > 0, 'Logger did not receive SDK records.');

$response = new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', 'OK');
expect_true($response instanceof ResponseInterface, 'Response does not implement PSR-7 ResponseInterface.');
expect_true($response->getHeaderLine('content-type') === 'application/json', 'Header lookup is not PSR-7 compatible.');
expect_true((string) $response->getBody() === '{"result_code":"success"}', 'Body stream does not expose response body.');
expect_true($response->withAddedHeader('X-Test', 'a')->withAddedHeader('X-Test', 'b')->getHeaderLine('x-test') === 'a, b', 'Header line formatting is not PSR compatible.');

$request = new Request('POST', 'https://pay.example.com/api/query.do', ['Content-Type' => 'application/json'], '{"foo":"bar"}');
expect_true($request instanceof RequestInterface, 'Request does not implement PSR-7 RequestInterface.');
expect_true($http instanceof ClientInterface, 'HTTP client does not implement PSR-18 ClientInterface.');
expect_true($request->getMethod() === 'POST', 'Request method mismatch.');
expect_true($request->getHeaderLine('content-type') === 'application/json', 'Request header lookup is not PSR-7 compatible.');
expect_true($request->withUri(new CongmingPay\Http\Uri('https://other.example.com/path'))->getHeaderLine('host') === 'other.example.com', 'Request Host header was not updated from URI.');

echo "OK\n";
