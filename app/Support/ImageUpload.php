<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer image uploads (sourcing requests). Validation rules check the real content (finfo MIME + image header), not
 * the extension. Accepted images are decoded and re-encoded with GD before they are stored: this drops EXIF/GPS
 * metadata and anything appended to the file (polyglots), and the stored name is random with an extension chosen from
 * the detected type, never from the client's file name.
 */
class ImageUpload
{
    public const MAX_KB = 5120;            // per file

    public const MAX_TOTAL_KB = 15360;     // per request

    public const MAX_FILES = 5;

    public const MAX_SIDE = 6000;          // px; also caps decoding memory (decompression bombs)

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Laravel rules for an `images` array field. */
    public static function rules(): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'images.*' => [
                'file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', array_keys(self::TYPES)),
                'dimensions:min_width=32,min_height=32,max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE,
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'images.max' => 'Please attach up to '.self::MAX_FILES.' images.',
            'images.*.max' => 'Each image must be 5 MB or smaller.',
            'images.*.mimetypes' => 'Images must be JPEG, PNG or WebP.',
            'images.*.dimensions' => 'Images must be between 32 and '.self::MAX_SIDE.' pixels on each side.',
        ];
    }

    /** @param  UploadedFile[]  $files */
    public static function assertTotalSize(array $files): void
    {
        $total = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files));
        if ($total > self::MAX_TOTAL_KB * 1024) {
            throw ValidationException::withMessages(['images' => 'Attached images must be 15 MB or smaller in total.']);
        }
    }

    /** Re-encode and store on the public disk. Returns the public URL. */
    public static function store(UploadedFile $file, string $dir): string
    {
        $mime = (string) $file->getMimeType();   // finfo on the content
        $ext = self::TYPES[$mime] ?? null;
        $info = @getimagesize($file->getRealPath());
        $img = ($ext && $info && $info[0] <= self::MAX_SIDE && $info[1] <= self::MAX_SIDE) ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;
        if (! $img) {
            throw ValidationException::withMessages(['images' => 'One of the images could not be read. Please use a JPEG, PNG or WebP photo.']);
        }

        ob_start();
        match ($ext) {
            'jpg' => imagejpeg($img, null, 85),
            'png' => (imagesavealpha($img, true) && imagepng($img, null, 6)),
            'webp' => imagewebp($img, null, 85),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        $path = trim($dir, '/').'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return Storage::disk('public')->url($path);
    }
}
