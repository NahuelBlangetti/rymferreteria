<?php

namespace App\Services\Tickets;

use App\Models\Sale;
use App\Models\SaleItem;

class SaleTicketEscPosBuilder
{
    /**
     * Ancho en caracteres para impresoras termicas de 58mm (fuente estandar).
     */
    private const WIDTH = 32;

    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const PAYMENT_LABELS = [
        'cash'     => 'Efectivo',
        'transfer' => 'Transferencia',
        'card'     => 'Tarjeta',
    ];

    public function build(Sale $sale): string
    {
        $sale->loadMissing('items');

        $ticket = self::ESC.'@'; // Reset impresora

        $ticket .= $this->centered('RyM Ferretería');
        $ticket .= $this->centered('Comprobante no válido como factura');
        $ticket .= $this->centered($sale->created_at->format('d/m/Y H:i'));
        $ticket .= $this->centered("Venta {$sale->sale_number}");
        $ticket .= $this->separator();

        foreach ($sale->items as $item) {
            $ticket .= $this->itemLine($item);
        }

        $ticket .= $this->separator();
        $ticket .= $this->totalLine('Subtotal', (float) $sale->subtotal);

        if ((float) $sale->discount > 0) {
            $ticket .= $this->totalLine('Descuento', (float) $sale->discount);
        }

        $ticket .= $this->totalLine('TOTAL', (float) $sale->total);
        $ticket .= $this->separator();

        $ticket .= "\n";
        $ticket .= 'Medio de pago: '.(self::PAYMENT_LABELS[$sale->payment_method] ?? $sale->payment_method)."\n";
        $ticket .= "\n";
        $ticket .= $this->centered('¡Gracias por su compra!');
        $ticket .= "\n\n\n";
        $ticket .= self::GS.'V'.chr(0); // Corte de papel

        return $ticket;
    }

    /**
     * Arma la linea de un item aprovechando el ancho disponible: si el
     * nombre entra junto con el importe en una sola linea, no gasta una
     * linea aparte solo para el detalle de cantidad/precio unitario (que
     * ademas es redundante cuando la cantidad es 1).
     */
    private function itemLine(SaleItem $item): string
    {
        $qty = (float) $item->quantity;
        $qtyLabel = $this->formatNumber($qty);
        $unitPrice = $this->formatNumber((float) $item->unit_price);
        $subtotal = $this->formatNumber((float) $item->subtotal);

        $head = $qty == 1.0
            ? $item->product_name
            : "{$qtyLabel} x {$item->product_name}";

        if (mb_strlen($head) + 1 + mb_strlen($subtotal) <= self::WIDTH) {
            return $this->padRight($head, self::WIDTH - mb_strlen($subtotal)).$subtotal."\n";
        }

        $detail = $qty == 1.0 ? '' : "{$qtyLabel} x {$unitPrice}";
        $detailLine = $this->padRight($detail, self::WIDTH - mb_strlen($subtotal)).$subtotal."\n";

        return implode("\n", $this->wrap($head))."\n".$detailLine;
    }

    /**
     * str_pad() cuenta bytes, no caracteres: con tildes/ñ (multibyte en
     * UTF-8) desalinea la columna de importes. Este helper rellena segun
     * ancho visual real.
     */
    private function padRight(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    /**
     * Parte $text en lineas de a lo sumo self::WIDTH caracteres, respetando
     * palabras completas (y cortando a la fuerza si una palabra sola supera
     * el ancho del ticket).
     *
     * @return list<string>
     */
    private function wrap(string $text): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            while (mb_strlen($word) > self::WIDTH) {
                $lines[] = mb_substr($word, 0, self::WIDTH);
                $word = mb_substr($word, self::WIDTH);
            }

            $candidate = $current === '' ? $word : "{$current} {$word}";

            if (mb_strlen($candidate) > self::WIDTH) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function totalLine(string $label, float $amount): string
    {
        $right = $this->formatNumber($amount);

        return $this->padRight($label.':', self::WIDTH - mb_strlen($right)).$right."\n";
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * Centra $text a mano en vez de usar el comando ESC/POS "ESC a" (justificar):
     * el firmware de esta impresora (clon OCPP-58H) no lo soporta y termina
     * imprimiendo los bytes del comando como texto literal en vez de
     * ejecutarlo. Envuelve el texto si no entra en el ancho del ticket.
     */
    private function centered(string $text): string
    {
        $out = '';

        foreach ($this->wrap($text) as $line) {
            $padding = max(0, intdiv(self::WIDTH - mb_strlen($line), 2));
            $out .= str_repeat(' ', $padding).$line."\n";
        }

        return $out;
    }

    private function separator(): string
    {
        return str_repeat('-', self::WIDTH)."\n";
    }
}
