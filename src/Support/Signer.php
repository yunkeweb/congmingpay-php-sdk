<?php

declare(strict_types=1);

namespace CongmingPay\Support;

final class Signer
{
    /**
     * Request signing rule from the document:
     * sort all request keys by name, join as k=v with &, append &key=secret,
     * then MD5 and uppercase.
     *
     * @param array<string, mixed> $params
     */
    public static function sign(array $params, string $key): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);

        $pieces = [];
        foreach ($params as $name => $value) {
            if ($value === null) {
                continue;
            }
            $pieces[] = $name . '=' . self::stringify($value);
        }

        $pieces[] = 'key=' . $key;

        return strtoupper(md5(implode('&', $pieces)));
    }

    /**
     * @param array<string, mixed> $params
     * @param string[] $fields
     */
    public static function callbackSign(array $params, string $key, array $fields): string
    {
        $pieces = [];
        foreach ($fields as $field) {
            $pieces[] = $field . '=' . self::stringify($params[$field] ?? '');
        }
        $pieces[] = 'key=' . $key;

        return strtoupper(md5(implode('&', $pieces)));
    }

    /**
     * @param mixed $value
     */
    private static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return (string) $value;
    }
}
