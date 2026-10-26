<?php

namespace App\Services\Scraping;

class SensitiveData
{
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = in_array(strtolower((string) $key), ['api_key', 'apikey', 'api-key', 'authorization', 'sgai-apikey', 'access_token', 'secret', 'password'], true)
                    ? '[REDACTED]' : self::clean($item);
            }
        } elseif (is_string($value)) {
            foreach (config('integrations.providers') as $provider) {
                if (filled($provider['api_key'])) {
                    $value = str_replace($provider['api_key'], '[REDACTED]', $value);
                }
            }
        }

        return $value;
    }
}
