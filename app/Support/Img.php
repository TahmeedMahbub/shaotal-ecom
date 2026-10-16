<?php

namespace App\Support;

/**
 * Responsive image URLs for Cloudinary and Unsplash. Stored URLs are never changed in the database;
 * transformations are added when a page is rendered. Mirrors window.imgUrl() in public/assets/js/data.js —
 * keep the two in sync. Any other host (and Cloudinary URLs that already carry a transformation) is returned untouched.
 */
class Img
{
    /** Cloudinary transformation components, e.g. "w_800", "f_auto", "c_fill". */
    private const CLD_TRANSFORM = '/^(?:ar|a|b|bo|c|co|d|dl|dpr|e|f|fl|g|h|l|o|pg|q|r|t|u|w|x|y|z)_[^\/]+$/i';

    public static function url(?string $url, int $width): ?string
    {
        if (! $url) {
            return $url;
        }
        $p = parse_url($url);
        if (! $p || empty($p['host']) || ! in_array($p['scheme'] ?? '', ['http', 'https'], true)) {
            return $url;
        }

        if ($p['host'] === 'res.cloudinary.com') {
            // /{cloud}/image/upload/{rest}
            if (! preg_match('#^(/[^/]+/image/upload/)(.+)$#', $p['path'] ?? '', $m)) {
                return $url;
            }
            $first = explode('/', $m[2], 2)[0];
            if (preg_match(self::CLD_TRANSFORM, $first)) {
                return $url; // already transformed on purpose
            }

            return $p['scheme'].'://'.$p['host'].$m[1].'f_auto,q_auto,c_limit,w_'.$width.'/'.$m[2].(isset($p['query']) ? '?'.$p['query'] : '');
        }

        if ($p['host'] === 'images.unsplash.com') {
            parse_str($p['query'] ?? '', $q);
            $q['w'] = $width;
            $q += ['q' => 75, 'auto' => 'format'];
            unset($q['h'], $q['ar']);

            return $p['scheme'].'://'.$p['host'].($p['path'] ?? '').'?'.http_build_query($q);
        }

        return $url;
    }

    /** Origin of a transformable CDN host (for <link rel=preconnect>), or null for anything else. */
    public static function origin(?string $url): ?string
    {
        $p = $url ? parse_url($url) : null;
        if ($p && in_array($p['host'] ?? '', ['res.cloudinary.com', 'images.unsplash.com'], true) && ($p['scheme'] ?? '') === 'https') {
            return 'https://'.$p['host'];
        }

        return null;
    }

    /** "url 320w, url 640w" for an <img srcset>; null when the URL is not transformable. */
    public static function srcset(?string $url, array $widths): ?string
    {
        if (! $url || self::url($url, $widths[0]) === $url) {
            return null;
        }

        return implode(', ', array_map(fn ($w) => self::url($url, $w).' '.$w.'w', $widths));
    }
}
