<?php

namespace App\Services\Labels;

use App\Models\Product;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductLabelZplBuilder
{
    /**
     * Etiqueta de 50x30mm a 203dpi (400x240 dots).
     */
    public function build(Product $product, int $copies = 1): string
    {
        if (blank($product->barcode)) {
            throw new InvalidArgumentException("El producto \"{$product->name}\" no tiene código de barras asignado.");
        }

        $copies = max(1, $copies);
        $name = $this->sanitize(Str::limit($product->name, 32, ''));
        $price = number_format((float) $product->sale_price, 2, ',', '.');
        $barcode = $this->sanitize((string) $product->barcode);

        return <<<ZPL
        ^XA
        ^PW400
        ^LL240
        ^CF0,26
        ^FO20,15^FB360,1,0,L^FD{$name}^FS
        ^CF0,40
        ^FO20,60^FD\$ {$price}^FS
        ^BY2,2,60
        ^FO20,115^BCN,60,Y,N,N
        ^FD{$barcode}^FS
        ^PQ{$copies}
        ^XZ

        ZPL;
    }

    /**
     * @param  iterable<array{0: Product, 1: int}>  $items
     */
    public function buildMany(iterable $items): string
    {
        $zpl = '';

        foreach ($items as [$product, $copies]) {
            $zpl .= $this->build($product, $copies);
        }

        return $zpl;
    }

    private function sanitize(string $value): string
    {
        // ^ y ~ son caracteres de control ZPL, no deben viajar en el contenido de un campo.
        return str_replace(['^', '~'], '', $value);
    }
}
