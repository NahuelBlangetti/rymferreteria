<?php

namespace App\Support;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida códigos de barras pensados para impresión CODE39 en impresora
 * térmica ESC/POS. Evita cargar nombres de producto u otros textos
 * que la impresora termina dibujando como letras.
 */
class ProductBarcode implements ValidationRule
{
    /** Caracteres CODE39 sin espacio (los espacios casi nunca vienen de un escáner). */
    public const ALLOWED_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-.$/+%';

    public const MIN_LENGTH = 4;

    public const MAX_LENGTH = 20;

    /**
     * Ancho imprimible de la Inkspire (58 mm / 384 puntos, ver
     * ProductLabelEscPosBuilder) con el ancho de módulo mínimo usado al
     * imprimir (GS w = 2). Un CODE39 de más de este largo no entra en el
     * papel con ninguna relación ancho/angosto habitual (2:1 a 3:1): el
     * firmware lo recorta o lo descarta sin avisar, así que el código
     * "se pierde" recién al imprimir, no al guardar. Los numéricos de
     * 12-13 dígitos no están sujetos a este límite porque se imprimen
     * como EAN13 nativo (mucho más compacto), no como CODE39.
     */
    public const MAX_CODE39_LENGTH = 10;

    public function __construct(private readonly ?int $ignoreProductId = null) {}

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value === '' ? null : $value;
    }

    /**
     * true si el código se imprime como EAN13 nativo (GS k m=2) en vez de
     * CODE39: numérico puro de 12 o 13 dígitos, el formato real de los
     * códigos de fábrica escaneados de un envase.
     */
    public static function isEan13(string $code): bool
    {
        return ctype_digit($code) && in_array(strlen($code), [12, 13], true);
    }

    /**
     * @return string|null Mensaje de error, o null si es válido / vacío.
     */
    public static function errorMessage(?string $value, ?int $ignoreProductId = null): ?string
    {
        $code = self::normalize($value);

        if ($code === null) {
            return null;
        }

        $length = strlen($code);

        if ($length < self::MIN_LENGTH) {
            return 'El código de barras es demasiado corto (mínimo ' . self::MIN_LENGTH . ' caracteres). Escaneá el código del producto.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'El código de barras es demasiado largo (máximo ' . self::MAX_LENGTH . ' caracteres). ¿Estás escribiendo el nombre del producto en vez del código?';
        }

        if (! preg_match('/^[' . preg_quote(self::ALLOWED_CHARS, '/') . ']+$/', $code)) {
            return 'El código de barras solo puede tener letras, números y los símbolos - . $ / + %. Sacá espacios y tildes.';
        }

        if (! preg_match('/\d/', $code)) {
            return 'El código de barras debe incluir al menos un número. No uses el nombre del producto.';
        }

        // Muchas letras seguidas sin números suelen ser un nombre mal cargado
        // (ej. ATEXPROFEXT/INT). Los códigos reales suelen ser numéricos o
        // alfanuméricos cortos con dígitos intercalados.
        if (preg_match('/[A-Z]{8,}/', $code)) {
            return 'Ese valor parece un nombre, no un código de barras. Escaneá el código del envase o la etiqueta del proveedor.';
        }

        if (! self::isEan13($code) && $length > self::MAX_CODE39_LENGTH) {
            return 'El código de barras es demasiado largo para imprimirse (máximo '.self::MAX_CODE39_LENGTH.' caracteres, salvo que sea un EAN13 de fábrica de 12-13 dígitos numéricos).';
        }

        $exists = Product::query()
            ->whereRaw('UPPER(barcode) = ?', [$code])
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($exists) {
            return 'Ese código de barras ya está asignado a otro producto.';
        }

        return null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = self::errorMessage(
            is_string($value) || $value === null ? $value : (string) $value,
            $this->ignoreProductId,
        );

        if ($message !== null) {
            $fail($message);
        }
    }
}
