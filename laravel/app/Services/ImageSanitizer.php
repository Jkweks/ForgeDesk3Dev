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

    /** Small JPEG thumbnail bytes (for PDFs), or null when the image can't be decoded. */
    public static function thumbnail(string $bytes, int $maxSide = 360, int $quality = 72): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($src = @imagecreatefromstring($bytes))) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $k = min(1, $maxSide / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, $quality);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out ?: null;
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
