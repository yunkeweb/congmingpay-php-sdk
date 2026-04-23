<?php

declare(strict_types=1);

require __DIR__ . '/../src/Support/Signer.php';
require __DIR__ . '/../src/CallbackVerifier.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Http/Response.php';
require __DIR__ . '/../src/Http/HttpClientInterface.php';
require __DIR__ . '/../src/CongmingPayClient.php';

use CongmingPay\CallbackVerifier;
use CongmingPay\Config;
use CongmingPay\CongmingPayClient;
use CongmingPay\Http\HttpClientInterface;
use CongmingPay\Http\Response;
use CongmingPay\Support\Signer;

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
};

$client = new CongmingPayClient(new Config('https://pay.example.com', 'pid', 'sid', 'secret'), $http);
$client->query(['order_id' => 'OID']);

expect_true($http->payload['program_id'] === 'pid', 'program_id was not injected.');
expect_true($http->payload['shop_id'] === 'sid', 'shop_id was not injected.');
expect_true(isset($http->payload['sign']), 'sign was not injected.');

echo "OK\n";
