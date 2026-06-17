<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            padding: 22px 28px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 120px;
        }
        .header-logo img {
            max-height: 40px;
            max-width: 110px;
        }
        .header-info {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }
        .header-title {
            font-size: 14px;
            font-weight: bold;
            color: #1d4ed8;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
        .confidential {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-size: 7px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead tr {
            background-color: #1d4ed8;
            color: #fff;
        }
        thead th {
            padding: 6px 7px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        thead th.right  { text-align: right; }
        thead th.center { text-align: center; }

        tbody tr:nth-child(even) { background-color: #f0f4ff; }
        tbody tr:nth-child(odd)  { background-color: #fff; }

        tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        tbody td.right  { text-align: right; }
        tbody td.center { text-align: center; }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 10px;
            font-size: 7.5px;
            background-color: #e5e7eb;
            color: #374151;
        }
        .stock-ok      { background-color: #d1fae5; color: #065f46; }
        .stock-warning { background-color: #fef3c7; color: #92400e; }
        .stock-danger  { background-color: #fee2e2; color: #991b1b; }

        .margin-ok      { background-color: #d1fae5; color: #065f46; }
        .margin-warning { background-color: #fef3c7; color: #92400e; }
        .margin-danger  { background-color: #fee2e2; color: #991b1b; }

        .price  { font-weight: bold; }
        .cost   { color: #6b7280; }
        .no-data { color: #aaa; font-style: italic; }

        /* Footer */
        .footer {
            margin-top: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 7px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            font-size: 7.5px;
            color: #888;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            font-size: 7.5px;
            color: #888;
        }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <div class="header-logo">
            <img src="{{ public_path('images/logo-full.png') }}" alt="Logo">
        </div>
        <div class="header-info">
            <div class="header-title">Inventario de Productos</div>
            <div class="header-subtitle">{{ config('app.name') }} &mdash; {{ now()->format('d/m/Y H:i') }}</div>
            <span class="confidential">Uso interno &mdash; Confidencial</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:22%">Producto</th>
                <th style="width:10%">Código</th>
                <th style="width:12%">Categoría</th>
                <th style="width:12%">Proveedor</th>
                <th class="center" style="width:7%">Unidad</th>
                <th class="right" style="width:10%">Costo</th>
                <th class="right" style="width:10%">Venta</th>
                <th class="center" style="width:8%">Margen</th>
                <th class="center" style="width:9%">Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $stockClass = match(true) {
                        $product->stock <= 0                  => 'stock-danger',
                        $product->stock <= $product->min_stock => 'stock-warning',
                        default                                => 'stock-ok',
                    };
                    $marginClass = match(true) {
                        $product->margin_percentage === null  => '',
                        $product->margin_percentage < 15     => 'margin-danger',
                        $product->margin_percentage < 25     => 'margin-warning',
                        default                               => 'margin-ok',
                    };
                @endphp
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>
                        @if ($product->barcode)
                            <span class="badge">{{ $product->barcode }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($product->category)
                            <span class="badge">{{ $product->category->name }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($product->supplier)
                            {{ $product->supplier->name }}
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td class="center">
                        {{ match($product->unit) {
                            'unidad' => 'Unid.',
                            'metro'  => 'Metro',
                            'm2'     => 'm²',
                            'kg'     => 'Kg',
                            'g'      => 'Gr',
                            'litro'  => 'Litro',
                            'caja'   => 'Caja',
                            'rollo'  => 'Rollo',
                            'par'    => 'Par',
                            'docena' => 'Doc.',
                            default  => $product->unit,
                        } }}
                    </td>
                    <td class="right cost">
                        ${{ number_format($product->cost_price, 2, ',', '.') }}
                    </td>
                    <td class="right price">
                        ${{ number_format($product->sale_price, 2, ',', '.') }}
                    </td>
                    <td class="center">
                        @if ($product->margin_percentage !== null)
                            <span class="badge {{ $marginClass }}">
                                {{ number_format($product->margin_percentage, 1) }}%
                            </span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td class="center">
                        <span class="badge {{ $stockClass }}">{{ $product->stock }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Documento generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }} hs. Precios en pesos argentinos (ARS).
        </div>
        <div class="footer-right">
            Total: {{ $products->count() }} producto(s)
        </div>
    </div>

</div>
</body>
</html>
