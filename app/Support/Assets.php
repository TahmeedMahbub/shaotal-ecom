<?php

namespace App\Support;

/** Static asset URLs versioned by file modification time, so they can be cached for a long time safely. */
class Assets
{
    private static array $memo = [];

    public static function url(string $path): string
    {
        $path = ltrim($path, '/');

        return self::$memo[$path] ??= asset($path).(is_file($f = public_path($path)) ? '?v='.filemtime($f) : '');
    }
}
