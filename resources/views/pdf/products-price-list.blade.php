<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            padding: 28px 32px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #e5350b;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 120px;
        }
        .header-logo img {
            max-height: 44px;
            max-width: 110px;
        }
        .header-info {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #e5350b;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead tr {
            background-color: #e5350b;
            color: #fff;
        }
        thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }

        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:nth-child(odd)  { background-color: #fff; }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #ebebeb;
            vertical-align: middle;
        }
        tbody td.right { text-align: right; }
        tbody td.center { text-align: center; }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            background-color: #e5e7eb;
            color: #374151;
        }
        .price {
            font-weight: bold;
            color: #1a1a1a;
        }
        .no-data { color: #aaa; font-style: italic; }

        /* Footer */
        .footer {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            font-size: 8px;
            color: #888;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            font-size: 8px;
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
            <div class="header-title">Lista de Precios</div>
            <div class="header-subtitle">{{ config('app.name') }} &mdash; {{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40%">Producto</th>
                <th style="width:15%">Código</th>
                <th style="width:20%">Categoría</th>
                <th class="center" style="width:10%">Unidad</th>
                <th class="right" style="width:15%">Precio</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
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
                    <td class="right price">
                        ${{ number_format($product->sale_price, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Los precios están expresados en pesos argentinos (ARS) e incluyen IVA.
        </div>
        <div class="footer-right">
            Total: {{ $products->count() }} producto(s)
        </div>
    </div>

</div>
</body>
</html>
