<?php

namespace MltStephane\LaravelAnalytics\Support;

/**
 * Static URL helpers.
 */
class Uri
{
    public static function hostname(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === false || $host === null ? null : $host;
    }

    public static function domainFrom(?string $url): ?string
    {
        return self::hostname($url);
    }

    public static function pathAndQuery(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if ($path === false || $path === null) {
            return null;
        }

        return $query ? $path.'?'.$query : $path;
    }

    public static function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }
}
