<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicTargetUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = strtolower(trim((string) parse_url($value, PHP_URL_HOST), '[]'));
        $port = parse_url($value, PHP_URL_PORT);
        $privateIp = filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $invalidNumericHost = preg_match('/^[0-9.]+$/', $host) && ! filter_var($host, FILTER_VALIDATE_IP);
        if ($host === '' || parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
            || ($port && ! in_array($port, [80, 443], true)) || $privateIp || $invalidNumericHost
            || str_starts_with($host, '0x') || in_array($host, ['localhost', 'localhost.localdomain'], true)
            || preg_match('/\.(localhost|local|internal)$/', $host)) {
            $fail('Usá una URL pública HTTP(S), sin credenciales y con puerto 80 o 443.');
        }
    }
}
