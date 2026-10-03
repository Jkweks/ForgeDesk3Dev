<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\Part;
use App\Support\Dimension;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the tiger-bridge /print payload for a drop-rack tag: profile image,
 * SKU (PARTID-FINISHCODE) and the length. A rack tag carries the floored
 * length; a scrap tag says SCRAP with the real length underneath.
 */
class DropLabel
{
    /** Printable area of the 1"x4" label at 203dpi, minus margins. */
    protected const IMAGE_BOX = 183;

    public static function sku(?string $name, ?string $finish): string
    {
        [$partNumber, $code] = Part::profileKey($name, $finish);

        return $code ? "{$partNumber}-{$code}" : (string) $partNumber;
    }

    /**
     * What the on-screen label preview (cut toast) shows: the same text as the printed tag, with the
     * photo as a URL rather than a printer bitmap.
     */
    public function preview(string $kind, ?string $name, ?string $finish, float $length): array
    {
        $payload = $this->payload($kind, $name, $finish, $length, withImage: false);

        return $payload + ['photoUrl' => Part::resolveProductFor($name, $finish)?->photo_url];
    }

    public function payload(string $kind, ?string $name, ?string $finish, float $length, bool $withImage = true): array
    {
        $isRack = $kind === 'drop';

        return array_filter([
            'kind' => $kind,
            'sku' => self::sku($name, $finish),
            'size' => $isRack
                ? Dimension::toFraction(DropPlanner::tagFor($length))
                : 'SCRAP',
            'detail' => $isRack ? null : 'too short - '.Dimension::toFraction($length).'"',
            'image' => $withImage ? $this->image(Part::resolveProductFor($name, $finish)?->photo_path) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * 1-bit bitmap of the profile photo as ZPL ^GFA hex, or null when there's no usable photo
     * (the label then simply prints without one).
     *
     * @return array{bytesPerRow: int, total: int, hex: string}|null
     */
    protected function image(?string $photoPath): ?array
    {
        if (! $photoPath || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            $source = $disk->exists($photoPath) ? @imagecreatefromstring($disk->get($photoPath)) : false;
        } catch (\Throwable) {
            return null;
        }

        if (! $source) {
            return null;
        }

        $box = self::IMAGE_BOX;
        $scale = min($box / imagesx($source), $box / imagesy($source));
        $w = max(1, (int) round(imagesx($source) * $scale));
        $h = max(1, (int) round(imagesy($source) * $scale));
        $bytesPerRow = intdiv($w + 7, 8);

        $canvas = imagecreatetruecolor($bytesPerRow * 8, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));

        $bytes = '';
        for ($y = 0; $y < $h; $y++) {
            for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $rgb = imagecolorat($canvas, $bx * 8 + $bit, $y);
                    $luma = 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255);
                    $byte = ($byte << 1) | ($luma < 160 ? 1 : 0);
                }
                $bytes .= chr($byte);
            }
        }

        return ['bytesPerRow' => $bytesPerRow, 'total' => strlen($bytes), 'hex' => strtoupper(bin2hex($bytes))];
    }
}
