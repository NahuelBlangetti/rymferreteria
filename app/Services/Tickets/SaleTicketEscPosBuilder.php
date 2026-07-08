<?php

namespace App\Services\Tickets;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Str;

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

        $ticket .= $this->centered(self::ESC.'!'.chr(24)."RyM Ferretería".self::ESC.'!'.chr(0));
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

        $ticket .= self::ESC.'!'.chr(8); // Bold
        $ticket .= $this->totalLine('TOTAL', (float) $sale->total);
        $ticket .= self::ESC.'!'.chr(0); // Bold off

        $ticket .= "\n";
        $ticket .= 'Medio de pago: '.(self::PAYMENT_LABELS[$sale->payment_method] ?? $sale->payment_method)."\n";
        $ticket .= "\n";
        $ticket .= $this->centered('¡Gracias por su compra!');
        $ticket .= "\n\n\n";
        $ticket .= self::GS.'V'.chr(0); // Corte de papel

        return $ticket;
    }

    private function itemLine(SaleItem $item): string
    {
        $qty = $this->formatNumber((float) $item->quantity);
        $subtotal = $this->formatNumber((float) $item->subtotal);
        $prefix = "{$qty} x ";

        $maxNameLength = max(1, self::WIDTH - strlen($subtotal) - strlen($prefix));
        $left = $prefix.Str::limit($item->product_name, $maxNameLength, '');

        return str_pad($left, self::WIDTH - strlen($subtotal)).$subtotal."\n";
    }

    private function totalLine(string $label, float $amount): string
    {
        $right = $this->formatNumber($amount);

        return str_pad($label.':', self::WIDTH - strlen($right)).$right."\n";
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function centered(string $text): string
    {
        return self::ESC.'a'.chr(1).$text."\n".self::ESC.'a'.chr(0);
    }

    private function separator(): string
    {
        return str_repeat('-', self::WIDTH)."\n";
    }
}
