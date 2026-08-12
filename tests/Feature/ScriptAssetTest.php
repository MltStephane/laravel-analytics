<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use MltStephane\LaravelAnalytics\Support\ScriptAsset;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class ScriptAssetTest extends TestCase
{
    public function test_hash_is_stable_and_content_driven(): void
    {
        $trackerHash = ScriptAsset::hash('tracker');

        $this->assertNotEmpty($trackerHash);
        $this->assertSame(substr(sha1_file(ScriptAsset::path('tracker')), 0, 12), $trackerHash);

        $dashboardHash = ScriptAsset::hash('dashboard');

        $this->assertNotEmpty($dashboardHash);
        $this->assertSame($dashboardHash, ScriptAsset::hash('dashboard'));
    }

    public function test_contents_match_file_on_disk(): void
    {
        $trackerContents = ScriptAsset::contents('tracker');
        $dashboardContents = ScriptAsset::contents('dashboard');

        $this->assertNotEmpty($trackerContents);
        $this->assertStringContainsString('window.analytics', $trackerContents);
        $this->assertStringContainsString('data-pageviews', $dashboardContents);
    }

    public function test_unknown_script_name_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScriptAsset::path('inconnu');
    }
}
