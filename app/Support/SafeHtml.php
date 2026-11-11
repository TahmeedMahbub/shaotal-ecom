<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for admin-authored rich text (product descriptions), which the product page renders as HTML.
 * Plain text (no tag-like "<x") is returned unchanged: the browser escapes it before display.
 * Anything else is parsed and rebuilt from allowed elements/attributes only, so script, event handlers, styles,
 * iframes and javascript:/data: links can never reach the page, while bold, lists, headings and links survive.
 */
class SafeHtml
{
    /** element => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [], 'small' => [], 'span' => [], 'div' => [], 'blockquote' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
    ];

    /** Removed together with everything inside them (others that are not allowed are unwrapped, keeping their text). */
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'template',
        'noscript', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'head', 'title',
        'video', 'audio', 'source', 'track', 'canvas', 'portal', 'dialog',
    ];

    public static function isHtml(?string $text): bool
    {
        return $text !== null && preg_match('/<[a-zA-Z!\/?]/', $text) === 1;
    }

    public static function clean(?string $text): string
    {
        $text = (string) $text;
        if (! self::isHtml($text)) {
            return $text;
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>'.$text.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->childNodes as $n) {
            if ($n instanceof DOMElement) {
                $root = $n;
                break;
            }
        }
        if (! $root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim($out);

        // Keep it recognisable as HTML for the page script (which renders HTML only when it sees a tag).
        return $out === '' || self::isHtml($out) ? $out : '<p>'.$out.'</p>';
    }

    private static function walk(DOMNode $node): void
    {
        // Copy first: the list changes while nodes are removed/unwrapped.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $child instanceof DOMElement) {
                $node->removeChild($child); // comments, CDATA, processing instructions

                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }
            if (! isset(self::ALLOWED[$tag])) {
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $keep = in_array($name, self::ALLOWED[$tag], true)
                    && (! in_array($name, ['href', 'src'], true) || self::safeUrl($attr->value, $name === 'href'));
                if (! $keep) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child);
        }
    }

    private static function safeUrl(string $url, bool $isLink): bool
    {
        // Browsers ignore whitespace/control characters inside schemes ("java\nscript:"), so compare without them.
        $u = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($u === '') {
            return false;
        }
        if (str_starts_with($u, 'https://') || str_starts_with($u, 'http://')) {
            return true;
        }
        if ($isLink && (str_starts_with($u, 'mailto:') || str_starts_with($u, 'tel:') || str_starts_with($u, '#'))) {
            return true;
        }

        // Same-site relative path, but not protocol-relative ("//evil.example").
        return str_starts_with($u, '/') && ! str_starts_with($u, '//') && ! str_starts_with($u, '/\\');
    }
}
