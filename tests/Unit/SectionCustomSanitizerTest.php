<?php

namespace Tests\Unit;

use App\Support\SectionCustomSanitizer;
use PHPUnit\Framework\TestCase;

class SectionCustomSanitizerTest extends TestCase
{
    public function test_rejects_http_library_urls(): void
    {
        $this->assertFalse(SectionCustomSanitizer::isHttpsUrl('http://example.com/a.js'));
        $this->assertFalse(SectionCustomSanitizer::isHttpsUrl('javascript:alert(1)'));
        $this->assertTrue(SectionCustomSanitizer::isHttpsUrl('https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js'));
    }

    public function test_drops_qr_and_unknown_keys(): void
    {
        $clean = SectionCustomSanitizer::sanitize([
            'qr' => ['mode' => 'custom', 'html' => '<p>nope</p>', 'css' => '', 'js' => '', 'libraries' => []],
            'evil' => ['mode' => 'custom', 'html' => 'x', 'css' => '', 'js' => '', 'libraries' => []],
            'couple' => ['mode' => 'custom', 'html' => '<h1>Hi</h1>', 'css' => '', 'js' => '', 'libraries' => []],
        ]);

        $this->assertArrayNotHasKey('qr', $clean);
        $this->assertArrayNotHasKey('evil', $clean);
        $this->assertSame('custom', $clean['couple']['mode']);
        $this->assertSame('<h1>Hi</h1>', $clean['couple']['html']);
    }

    public function test_strips_non_https_libraries_and_keeps_https(): void
    {
        $clean = SectionCustomSanitizer::sanitize([
            'gallery' => [
                'mode' => 'existing',
                'html' => '',
                'css' => '',
                'js' => '',
                'libraries' => [
                    ['id' => 'bad', 'name' => 'bad', 'src' => 'http://x.com/a.js', 'kind' => 'js'],
                    ['id' => 'ok', 'name' => 'ok', 'src' => 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js', 'kind' => 'js'],
                ],
            ],
        ]);

        $this->assertCount(1, $clean['gallery']['libraries']);
        $this->assertSame('ok', $clean['gallery']['libraries'][0]['id']);
    }

    public function test_allows_custom_section_keys(): void
    {
        $clean = SectionCustomSanitizer::sanitize([
            'custom:abc-1' => ['mode' => 'custom', 'html' => 'ok', 'css' => '', 'js' => '', 'libraries' => []],
        ]);

        $this->assertArrayHasKey('custom:abc-1', $clean);
    }
}
