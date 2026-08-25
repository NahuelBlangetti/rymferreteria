<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.styles')
    <style>
        tbody tr { page-break-inside: avoid; }
        tbody td { vertical-align: middle; }
        .barcode-cell { text-align: center; padding-top: 10px; padding-bottom: 10px; }
        .barcode-img { height: 42px; width: auto; }
        .barcode-hri {
            display: block;
            font-size: 8px;
            letter-spacing: 0.8px;
            margin-top: 3px;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
<div class="page">

    @include('pdf.partials.header', [
        'title'     => $pdfTitle,
        'dateLabel' => now()->format('d/m/Y'),
    ])

    <table>
        <thead>
            <tr>
                <th style="width:58%">Producto</th>
                <th class="center" style="width:42%">Código de barras</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $barcodeImage = \App\Support\ProductBarcodeImage::dataUri($product->barcode);
                @endphp
                <tr>
                    <td>{{ $product->name }}</td>
                    <td class="barcode-cell">
                        @if ($barcodeImage)
                            <img class="barcode-img" src="{{ $barcodeImage }}" alt="{{ $product->barcode }}">
                            <span class="barcode-hri">{{ $product->barcode }}</span>
                        @elseif ($product->barcode)
                            <span class="badge">{{ $product->barcode }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', [
        'footerNote' => 'Listado de productos con código de barras interno. Las barras se pueden escanear.',
    ])

</div>
</body>
</html>
