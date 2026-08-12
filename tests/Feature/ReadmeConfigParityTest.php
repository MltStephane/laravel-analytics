<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use PHPUnit\Framework\TestCase;

class ReadmeConfigParityTest extends TestCase
{
    public function test_every_config_key_is_documented_in_the_readme(): void
    {
        $configPath = __DIR__.'/../../config/analytics.php';
        $readmePath = __DIR__.'/../../README.md';

        $this->assertFileExists($configPath);
        $this->assertFileExists($readmePath);

        $config = require $configPath;
        $readme = (string) file_get_contents($readmePath);

        foreach ($this->flattenKeys($config) as $key) {
            $this->assertStringContainsString(
                '`'.$key.'`',
                $readme,
                "Config key [{$key}] is documented in config/analytics.php but missing from README.md.",
            );
        }
    }

    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $keys = [...$keys, ...$this->flattenKeys($value, $fullKey)];
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }
}
