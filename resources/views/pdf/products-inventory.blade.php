<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.styles', [
        'fontSize'    => '9px',
        'pagePadding' => '22px 28px',
        'thPadding'   => '6px 7px',
        'thFontSize'  => '8px',
        'tdPadding'   => '5px 7px',
        'badgeFontSize' => '7.5px',
    ])
</head>
<body>
<div class="page">

    @php
        $supplierNames = $products->pluck('supplier.name')->filter()->unique();
        $supplierLabel = match (true) {
            $supplierNames->count() === 1 => 'Proveedor: '.$supplierNames->first(),
            $supplierNames->count() > 1   => 'Varios proveedores',
            default                        => null,
        };
    @endphp

    @include('pdf.partials.header', [
        'title'     => $pdfTitle,
        'subtitle'  => $supplierLabel,
        'dateLabel' => now()->format('d/m/Y H:i') . ' hs.',
        'audience'  => 'Proveedores',
    ])

    <table>
        <thead>
            <tr>
                <th style="width:55%">Producto</th>
                <th style="width:25%">SKU proveedor</th>
                <th class="center" style="width:20%">Cant. solicitada</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>
                        @if ($product->sku)
                            <span class="badge">{{ $product->sku }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td class="center">
                        @if ($product->requested_quantity !== null)
                            <span class="badge qty-requested">{{ \App\Models\Product::formatQuantity($product->requested_quantity) }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', [
        'footerNote' => 'Pedido a proveedor. La cantidad solicitada es lo que se espera recibir. Precios en pesos argentinos (ARS).',
    ])

</div>
</body>
</html>
