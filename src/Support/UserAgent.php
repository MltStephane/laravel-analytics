<?php

namespace MltStephane\LaravelAnalytics\Support;

use DeviceDetector\DeviceDetector;

/**
 * Thin wrapper around matomo/device-detector.
 */
class UserAgent
{
    /**
     * Parse a user agent string.
     *
     * @return array{browser: string|null, browser_version: string|null, os: string|null, device: string|null, device_type: string|null, is_bot: bool}
     */
    public static function parse(?string $userAgent): array
    {
        $empty = [
            'browser' => null,
            'browser_version' => null,
            'os' => null,
            'device' => null,
            'device_type' => null,
            'is_bot' => false,
        ];

        if ($userAgent === null || trim($userAgent) === '') {
            return $empty;
        }

        try {
            $info = DeviceDetector::getInfoFromUserAgent($userAgent);
        } catch (\Throwable $e) {
            return $empty;
        }

        // For bots, getInfoFromUserAgent() returns only ['user_agent', 'bot'].
        $isBot = array_key_exists('bot', $info) && is_array($info['bot']);

        $deviceType = $info['device']['type'] ?? null;

        if (! in_array($deviceType, ['desktop', 'smartphone', 'tablet'], true)) {
            $deviceType = null;
        }

        return [
            'browser' => self::nullIfEmpty($info['client']['name'] ?? null),
            'browser_version' => self::nullIfEmpty(substr((string) ($info['client']['version'] ?? ''), 0, 20)),
            'os' => self::nullIfEmpty($info['os']['name'] ?? null),
            'device' => self::nullIfEmpty($info['device']['brand'] ?? null),
            'device_type' => $deviceType,
            'is_bot' => $isBot,
        ];
    }

    protected static function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }
}
