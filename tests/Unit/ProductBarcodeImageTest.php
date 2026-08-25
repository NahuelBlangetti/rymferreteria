<?php

namespace Tests\Unit;

use App\Support\ProductBarcodeImage;
use PHPUnit\Framework\TestCase;
use Picqer\Barcode\BarcodeGenerator;

class ProductBarcodeImageTest extends TestCase
{
    public function test_internal_code_renders_as_code39_png(): void
    {
        $png = ProductBarcodeImage::png('RYM816');

        $this->assertNotNull($png);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertSame(BarcodeGenerator::TYPE_CODE_39, ProductBarcodeImage::typeFor('RYM816'));
        $this->assertNotFalse(imagecreatefromstring($png));
    }

    public function test_factory_ean13_renders_as_ean13_png(): void
    {
        $png = ProductBarcodeImage::png('5901234123457');

        $this->assertNotNull($png);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertSame(BarcodeGenerator::TYPE_EAN_13, ProductBarcodeImage::typeFor('5901234123457'));
        $this->assertNotFalse(imagecreatefromstring($png));
    }

    public function test_data_uri_wraps_png(): void
    {
        $uri = ProductBarcodeImage::dataUri('rym816');

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $decoded = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);

        $this->assertNotFalse($decoded);
        $this->assertSame("\x89PNG", substr($decoded, 0, 4));
    }

    public function test_empty_code_returns_null(): void
    {
        $this->assertNull(ProductBarcodeImage::png(null));
        $this->assertNull(ProductBarcodeImage::png('   '));
        $this->assertNull(ProductBarcodeImage::dataUri(null));
        $this->assertNull(ProductBarcodeImage::typeFor(null));
    }

    public function test_invalid_characters_return_null(): void
    {
        $this->assertNull(ProductBarcodeImage::png('@@@'));
    }

    public function test_ean13_with_bad_checksum_falls_back_to_code39(): void
    {
        $png = ProductBarcodeImage::png('5901234123450');

        $this->assertNotNull($png);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertSame(BarcodeGenerator::TYPE_EAN_13, ProductBarcodeImage::typeFor('5901234123450'));
        $this->assertNotFalse(imagecreatefromstring($png));
    }
}
