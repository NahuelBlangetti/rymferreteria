<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode39;
use Picqer\Barcode\Types\TypeEan13;
use Throwable;

/**
 * Genera la imagen PNG de un código de barras para el PDF.
 * Misma heurística que la etiqueta térmica: EAN13 nativo si el valor
 * es numérico de 12-13 dígitos; si no, CODE39 (códigos internos tipo RYM816).
 */
final class ProductBarcodeImage
{
    private const WIDTH_FACTOR = 2;

    private const HEIGHT = 56;

    /** Margen blanco a cada lado (ISO: ≥ 10 módulos). Módulo = WIDTH_FACTOR px. */
    private const QUIET_ZONE_X = 20;

    private const QUIET_ZONE_Y = 4;

    public static function dataUri(?string $code): ?string
    {
        $png = self::png($code);

        return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
    }

    public static function png(?string $code): ?string
    {
        $normalized = ProductBarcode::normalize($code);

        if ($normalized === null) {
            return null;
        }

        try {
            return self::render($normalized, ProductBarcode::isEan13($normalized));
        } catch (Throwable) {
            if (! ProductBarcode::isEan13($normalized)) {
                return null;
            }

            try {
                return self::render($normalized, false);
            } catch (Throwable) {
                return null;
            }
        }
    }

    private static function render(string $code, bool $ean13): string
    {
        $barcode = $ean13
            ? (new TypeEan13)->getBarcode($code)
            : (new TypeCode39)->getBarcode($code);

        $renderer = new PngRenderer;
        $renderer->useGd();
        $renderer->setForegroundColor([0, 0, 0]);
        $renderer->setBackgroundColor([255, 255, 255]);

        $png = $renderer->render(
            $barcode,
            $barcode->getWidth() * self::WIDTH_FACTOR,
            self::HEIGHT,
        );

        return self::withQuietZone($png);
    }

    private static function withQuietZone(string $png): string
    {
        $source = imagecreatefromstring($png);

        if ($source === false) {
            return $png;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor(
            $width + (self::QUIET_ZONE_X * 2),
            $height + (self::QUIET_ZONE_Y * 2),
        );

        if ($canvas === false) {
            imagedestroy($source);

            return $png;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, self::QUIET_ZONE_X, self::QUIET_ZONE_Y, 0, 0, $width, $height);

        ob_start();
        imagepng($canvas);
        $out = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $out === false ? $png : $out;
    }

    public static function typeFor(?string $code): ?string
    {
        $normalized = ProductBarcode::normalize($code);

        if ($normalized === null) {
            return null;
        }

        return ProductBarcode::isEan13($normalized)
            ? BarcodeGenerator::TYPE_EAN_13
            : BarcodeGenerator::TYPE_CODE_39;
    }
}
