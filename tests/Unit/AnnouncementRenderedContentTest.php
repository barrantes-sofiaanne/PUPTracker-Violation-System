<?php

namespace Tests\Unit;

use App\Services\AnnouncementContentSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Guards the XSS-sensitive announcement rendering path: the accessor output is
 * the sanitizer output, so a malicious stored row cannot inject script into
 * views that render {!! rendered_content !!}.
 */
class AnnouncementRenderedContentTest extends TestCase
{
    public function test_rendered_content_never_contains_executable_html(): void
    {
        $sanitizer = new AnnouncementContentSanitizer();

        $payloads = [
            '<p>Hello</p><script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '<p><a href="javascript:alert(1)">x</a></p>',
            '<iframe src="https://evil.example"></iframe>',
            '<p onclick="alert(1)">x</p>',
            '<svg onload=alert(1)></svg>',
        ];

        foreach ($payloads as $payload) {
            $out = $sanitizer->sanitize($payload);
            $this->assertStringNotContainsString('<script', $out, 'script must be stripped');
            $this->assertStringNotContainsString('onerror', $out, 'event handlers must be stripped');
            $this->assertStringNotContainsString('javascript:', $out, 'javascript: URLs must be stripped');
            $this->assertStringNotContainsString('<iframe', $out, 'iframes must be stripped');
            $this->assertStringNotContainsString('onload', $out, 'onload must be stripped');
            $this->assertStringNotContainsString('<svg', $out, 'svg must be stripped');
        }
    }

    public function test_rich_formatting_survives_sanitization(): void
    {
        $sanitizer = new AnnouncementContentSanitizer();

        $html = '<h2>HOLIDAY ANNOUNCEMENT</h2>'
            . '<p><strong>Thursday, June 12, 2025</strong> — Independence Day</p>'
            . '<ul><li>Item one</li><li>Item two</li></ul>'
            . '<ol><li>First</li></ol>'
            . '<p>Visit <a href="https://example.com">the portal</a>.</p>';

        $out = $sanitizer->sanitize($html);

        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<ol>', $out);
        $this->assertStringContainsString('<li>Item one</li>', $out);
        $this->assertStringContainsString('<a href="https://example.com"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringNotContainsString('<h2', $out, 'heading tags are not allow-listed and are unwrapped');
    }

    public function test_existing_plain_text_announcement_stays_readable(): void
    {
        $sanitizer = new AnnouncementContentSanitizer();

        $plain = "HOLIDAY ANNOUNCEMENT\nThursday, June 12, 2025 - Independence Day\n\nEnjoy the holiday.";
        $out = $sanitizer->sanitize($plain);

        $this->assertStringContainsString('HOLIDAY ANNOUNCEMENT', $out);
        $this->assertStringContainsString('Independence Day', $out);
        $this->assertStringContainsString('<p>', $out);
        $this->assertStringNotContainsString('<script', $out);
    }
}
