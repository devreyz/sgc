<?php

namespace App\Services\Services;

use Illuminate\Validation\ValidationException;

class ServiceEvidenceImageOptimizer
{
    private const MAX_BYTES = 200 * 1024;

    public function optimize(string $bytes, string $mime, string $field): array
    {
        if ($mime === 'application/pdf') {
            return [$bytes, $mime, 'pdf'];
        }

        $dimensions = @getimagesizefromstring($bytes);
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 25_000_000) {
            throw ValidationException::withMessages([$field => 'Envie uma imagem válida de até 25 megapixels.']);
        }
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        };
        $longEdge = max($dimensions[0], $dimensions[1]);
        if (strlen($bytes) <= self::MAX_BYTES && $longEdge <= 2048) {
            return [$bytes, $mime, $extension];
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages([$field => 'A imagem enviada não pôde ser lida.']);
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $smallest = null;

        for ($edge = min($longEdge, 1600); ; $edge = max(720, (int) ($edge * .8))) {
            $ratio = $edge / $longEdge;
            $scaled = $edge === $longEdge ? $image
                : imagescale($image, max(1, (int) round($dimensions[0] * $ratio)), max(1, (int) round($dimensions[1] * $ratio)), IMG_BICUBIC_FIXED);
            if (! $scaled) {
                break;
            }
            imagesavealpha($scaled, true);
            foreach ([82, 74, 66, 58, 48] as $quality) {
                ob_start();
                $encoded = imagewebp($scaled, null, $quality);
                $result = (string) ob_get_clean();
                if (! $encoded || $result === '') {
                    continue;
                }
                if (strlen($result) <= self::MAX_BYTES) {
                    return [$result, 'image/webp', 'webp'];
                }
                if ($smallest === null || strlen($result) < strlen($smallest)) {
                    $smallest = $result;
                }
            }
            if ($edge <= 720) {
                break;
            }
        }

        if ($smallest === null) {
            throw ValidationException::withMessages([$field => 'Não foi possível comprimir a imagem enviada.']);
        }

        return [$smallest, 'image/webp', 'webp'];
    }
}
