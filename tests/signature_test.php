<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CongmingPay\CallbackVerifier;
use CongmingPay\Config;
use CongmingPay\CongmingPayClient;
use CongmingPay\Http\HttpClientInterface;
use CongmingPay\Http\Request;
use CongmingPay\Http\Response;
use CongmingPay\Support\Signer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

$http = new class implements HttpClientInterface {
    /** @var array<string, mixed> */
    public array $payload = [];

    public function postJson(string $url, array $payload, array $headers = []): Response
    {
        $this->payload = $payload;

        return new Response(200, [], '{"result_code":"success"}', ['result_code' => 'success']);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200, [], '{"result_code":"success"}', ['result_code' => 'success']);
    }
};

$client = new CongmingPayClient(new Config('https://pay.example.com', 'pid', 'sid', 'secret'), $http);
$client->query(['order_id' => 'OID']);

expect_true($http->payload['program_id'] === 'pid', 'program_id was not injected.');
expect_true($http->payload['shop_id'] === 'sid', 'shop_id was not injected.');
expect_true(isset($http->payload['sign']), 'sign was not injected.');

$response = new Response(200, ['Content-Type' => 'application/json'], '{"result_code":"success"}', ['result_code' => 'success']);
expect_true($response instanceof ResponseInterface, 'Response does not implement PSR-7 ResponseInterface.');
expect_true($response->getHeaderLine('content-type') === 'application/json', 'Header lookup is not PSR-7 compatible.');
expect_true((string) $response->getBody() === '{"result_code":"success"}', 'Body stream does not expose response body.');

$request = new Request('POST', 'https://pay.example.com/api/query.do', ['Content-Type' => 'application/json'], '{"foo":"bar"}');
expect_true($request instanceof RequestInterface, 'Request does not implement PSR-7 RequestInterface.');
expect_true($http instanceof ClientInterface, 'HTTP client does not implement PSR-18 ClientInterface.');
expect_true($request->getMethod() === 'POST', 'Request method mismatch.');
expect_true($request->getHeaderLine('content-type') === 'application/json', 'Request header lookup is not PSR-7 compatible.');

echo "OK\n";
