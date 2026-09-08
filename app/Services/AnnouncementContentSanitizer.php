<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Server-side sanitizer for announcement rich-text content.
 *
 * Announcement content may contain a limited, safe subset of HTML produced by
 * the Quill editor (bold, italic, underline, ordered/bulleted lists, links,
 * paragraphs and line breaks). Arbitrary HTML from the browser is NEVER trusted
 * — this service strips anything outside that allow-list and neutralizes
 * executable payloads (script/iframe/object/embed, event-handler attributes,
 * and javascript:/data: URLs).
 */
class AnnouncementContentSanitizer
{
    /**
     * Allowed element names (lowercase).
     */
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'em',
        'u',
        'ol',
        'ul',
        'li',
        'a',
    ];

    /**
     * Allowed attributes per tag. `a` only allows href (plus target/rel are
     * normalized by the sanitizer) and `li` may carry data-list for Quill
     * checklists.
     */
    private const ALLOWED_ATTRS = [
        'a' => ['href'],
        'li' => ['data-list'],
    ];

    /**
     * Allowed URI schemes for links.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Sanitize raw rich-text HTML to the allowed subset.
     */
    public function sanitize(?string $html): string
    {
        $html = (string) $html;
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // If the content contains no angle brackets at all, treat it as legacy
        // plain text and convert line breaks to <p>/<br> so it renders nicely.
        if (! str_contains($html, '<')) {
            return $this->plainTextToHtml($html);
        }

        $dom = new DOMDocument();

        // Suppress warnings from malformed HTML fragments.
        $prev = libxml_use_internal_errors(true);
        // Wrap in a root element so fragment HTML parses reliably.
        $loaded = $dom->loadHTML(
            '<html><body><div id="ann-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            return $this->plainTextToHtml(strip_tags($html));
        }

        $root = $dom->getElementById('ann-root');

        if (! $root) {
            return $this->plainTextToHtml(strip_tags($html));
        }

        $this->sanitizeNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        // Decode entities that DOMDocument double-encodes (e.g. &amp;).
        $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($out);
    }

    /**
     * Recursively sanitize a DOM node in place.
     */
    private function sanitizeNode(DOMNode $node): void
    {
        // Walk children first (a copy is needed because removal mutates).
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Disallowed element: unwrap it, keeping its (sanitized)
                    // text content, unless it is a disallowed void/script-like
                    // node where unwrapping is dangerous. script/style/iframe/
                    // object/embed are simply removed.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'link', 'meta'], true)) {
                        $child->parentNode->removeChild($child);
                        continue;
                    }

                    $this->unwrapElement($child);
                    continue;
                }

                // Allowed element: scrub attributes.
                $this->scrubAttributes($child);
                $this->sanitizeNode($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $child->parentNode->removeChild($child);
            }
        }
    }

    /**
     * Replace an element with its child nodes (unwrap), preserving text.
     */
    private function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        // Recurse first to sanitize grandchildren, then move children up.
        $this->sanitizeNode($element);

        $children = [];
        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * Remove disallowed attributes and sanitize allowed ones.
     */
    private function scrubAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        $remove = [];
        foreach ($element->attributes as $attr) {
            $name = strtolower($attr->nodeName);

            // Always remove event handlers and anything not allow-listed.
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $remove[] = $attr->nodeName;
                continue;
            }

            // `a[href]`: only allow safe URL schemes.
            if ($tag === 'a' && $name === 'href') {
                $href = trim((string) $attr->nodeValue);
                if (! $this->isSafeUrl($href)) {
                    $remove[] = $attr->nodeName;
                }
            }
        }

        foreach ($remove as $attrName) {
            $element->removeAttribute($attrName);
        }

        // Normalize safe anchors to open in a new tab safely.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Whether a URL is safe to keep in an anchor href.
     */
    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // Protocol-relative (//example.com) is treated as HTTPS.
        if (str_starts_with($url, '//')) {
            return true;
        }

        // Strip leading control/whitespace (classic javascript: bypass).
        $url = ltrim($url, " \t\n\r\0\x0B");
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === null || $scheme === '') {
            // No scheme: allow relative and fragment/anchor-only links.
            if (str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, '?')) {
                return true;
            }

            return false;
        }

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /**
     * Convert legacy plain text into safe <p> paragraphs / <br> line breaks.
     */
    private function plainTextToHtml(string $text): string
    {
        // Remove any tags a legacy plain-text row could carry to avoid double
        // rendering and XSS.
        $text = strip_tags($text);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $paragraphs = [];
        $buffer = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if (! empty($buffer)) {
                    $paragraphs[] = '<p>' . implode('<br>', $buffer) . '</p>';
                    $buffer = [];
                }
                continue;
            }
            $buffer[] = e($line);
        }

        if (! empty($buffer)) {
            $paragraphs[] = '<p>' . implode('<br>', $buffer) . '</p>';
        }

        return implode('', $paragraphs);
    }
}
