<?php

namespace Tests\Unit;

use App\Services\AnnouncementContentSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Server-side sanitization for announcement rich-text content.
 *
 * Rich formatting must survive, but scripts, event handlers, dangerous
 * elements, and unsafe URL schemes must be stripped. Legacy plain-text rows
 * must be converted to safe paragraphs.
 */
class AnnouncementContentSanitizerTest extends TestCase
{
    private AnnouncementContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new AnnouncementContentSanitizer();
    }

    public function test_allows_bold_italic_and_underline(): void
    {
        $out = $this->sanitizer->sanitize('<p>Hello <strong>bold</strong>, <em>italic</em>, <u>underline</u></p>');
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<em>', $out);
        $this->assertStringContainsString('<u>', $out);
    }

    public function test_allows_ordered_and_bulleted_lists(): void
    {
        $out = $this->sanitizer->sanitize('<ul><li>one</li><li>two</li></ul><ol><li>first</li></ol>');
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
        $this->assertStringContainsString('<ol>', $out);
    }

    public function test_allows_safe_links_and_normalizes_target(): void
    {
        $out = $this->sanitizer->sanitize('<p><a href="https://example.com">site</a></p>');
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function test_strips_script(): void
    {
        $out = $this->sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_strips_iframe_object_embed(): void
    {
        $out = $this->sanitizer->sanitize('<p>Hello</p><iframe src="https://evil.example"></iframe><object data="x"></object><embed src="y">');
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<object', $out);
        $this->assertStringNotContainsString('<embed', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $out = $this->sanitizer->sanitize('<p onclick="alert(1)">Hello</p>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_strips_javascript_urls(): void
    {
        $out = $this->sanitizer->sanitize('<p><a href="javascript:alert(1)">click</a></p>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_strips_unknown_tags_but_keeps_text(): void
    {
        $out = $this->sanitizer->sanitize('<p>Hello <span>world</span> <marquee>now</marquee></p>');
        $this->assertStringNotContainsString('<span', $out);
        $this->assertStringNotContainsString('<marquee', $out);
        $this->assertStringContainsString('Hello', $out);
        $this->assertStringContainsString('world', $out);
    }

    public function test_converts_legacy_plain_text_to_paragraphs(): void
    {
        $out = $this->sanitizer->sanitize("Line one\nLine two\n\nParagraph two");
        $this->assertStringContainsString('<p>Line one<br>Line two</p>', $out);
        $this->assertStringContainsString('<p>Paragraph two</p>', $out);
    }

    public function test_empty_and_plain_strings_are_handled(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
        $out = $this->sanitizer->sanitize('Just plain text with <b>escaped</b> looks');
        $this->assertStringNotContainsString('<b>', $out);
    }
}
