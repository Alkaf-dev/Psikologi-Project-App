<?php

namespace App\Support;

final class ExternalUrl
{
    public static function http(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
