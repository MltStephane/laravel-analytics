<?php

namespace MltStephane\LaravelAnalytics\Support;

/**
 * Static helpers around the package's static JavaScript assets.
 */
final class ScriptAsset
{
    /**
     * @var array<string, string>
     */
    private const FILES = [
        'tracker' => __DIR__.'/../../resources/js/analytics.js',
        'dashboard' => __DIR__.'/../../resources/js/dashboard.js',
    ];

    /**
     * @var array<string, string>
     */
    private static array $hashes = [];

    public static function path(string $name): string
    {
        if (! isset(self::FILES[$name])) {
            throw new \InvalidArgumentException("Unknown script asset: {$name}");
        }

        return self::FILES[$name];
    }

    public static function contents(string $name): string
    {
        $contents = @file_get_contents(self::path($name));

        return $contents === false ? '' : $contents;
    }

    public static function hash(string $name): string
    {
        if (! isset(self::$hashes[$name])) {
            $hash = sha1_file(self::path($name));
            self::$hashes[$name] = $hash === false ? 'dev' : substr($hash, 0, 12);
        }

        return self::$hashes[$name];
    }
}
