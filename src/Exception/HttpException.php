<?php

declare(strict_types=1);

namespace CongmingPay\Exception;

use Psr\Http\Client\ClientExceptionInterface;

class HttpException extends CongmingPayException implements ClientExceptionInterface
{
}
