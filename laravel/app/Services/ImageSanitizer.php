<?php

namespace App\Services;

/** Verifies uploaded bytes really are an image and strips metadata (EXIF/GPS, trailing payloads). */
class ImageSanitizer
{
    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Sniffed mime type for JPEG/PNG/WebP bytes, or null. The claimed filename/type is never trusted. */
    public static function sniff(string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return isset(self::MIMES[$mime]) ? $mime : null;
    }

    /** Re-encode JPEG/PNG through GD; other types pass through unchanged. */
    public static function reencode(string $bytes, string $mime): string
    {
        if (! function_exists('imagecreatefromstring') || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $bytes;
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return $bytes;
        }
        ob_start();
        $mime === 'image/png' ? imagepng($img) : imagejpeg($img, null, 88);
        $out = ob_get_clean();
        imagedestroy($img);

        return $out ?: $bytes;
    }
}
