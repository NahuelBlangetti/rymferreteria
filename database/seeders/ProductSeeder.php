<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all()->keyBy('name');

        $cambre    = $suppliers['Distribuidora Cambre S.A.'] ?? null;
        $central   = $suppliers['Ferretería Central S.R.L.'] ?? null;
        $lubri     = $suppliers['Lubricantes del Sur S.A.'] ?? null;
        $stanley   = $suppliers['Distribuidora Stanley Argentina'] ?? null;
        $plastik   = $suppliers['Plastik S.A. - Plomería'] ?? null;

        // [nombre, categoría, costo, margen%, unidad, stock, min_stock, proveedor]
        $products = [

            // ── HERRAMIENTAS MANUALES ────────────────────────────────────────
            ['Martillo carpintero 16 oz',             'Herramientas Manuales',   6500,  32, 'unidad', 8,  3, $stanley],
            ['Martillo demoledor 1.5 kg',             'Herramientas Manuales',   9200,  30, 'unidad', 5,  2, $stanley],
            ['Destornillador plano 6"',               'Herramientas Manuales',   2100,  38, 'unidad', 15, 5, $central],
            ['Destornillador Phillips 6"',            'Herramientas Manuales',   2100,  38, 'unidad', 15, 5, $central],
            ['Set destornilladores 6 piezas',         'Herramientas Manuales',   7200,  32, 'unidad', 6,  2, $stanley],
            ['Alicate universal 8"',                  'Herramientas Manuales',   4800,  32, 'unidad', 10, 4, $stanley],
            ['Alicate de corte 6"',                   'Herramientas Manuales',   3600,  35, 'unidad', 8,  3, $stanley],
            ['Pinza de punta 6"',                     'Herramientas Manuales',   3800,  35, 'unidad', 8,  3, $stanley],
            ['Llave ajustable 12"',                   'Herramientas Manuales',   5800,  30, 'unidad', 6,  2, $central],
            ['Llave ajustable 8"',                    'Herramientas Manuales',   3800,  32, 'unidad', 8,  3, $central],
            ['Set llaves combinadas 8 piezas',        'Herramientas Manuales',  18500,  28, 'unidad', 4,  2, $stanley],
            ['Cinta métrica 5m',                      'Herramientas Manuales',   2400,  38, 'unidad', 12, 4, $stanley],
            ['Cinta métrica 8m',                      'Herramientas Manuales',   3200,  35, 'unidad', 8,  3, $stanley],
            ['Nivel torpedo 25cm',                    'Herramientas Manuales',   3500,  35, 'unidad', 6,  2, $central],
            ['Nivel 60 cm',                           'Herramientas Manuales',   5200,  30, 'unidad', 5,  2, $central],
            ['Sierra manual 26"',                     'Herramientas Manuales',   9800,  28, 'unidad', 4,  2, $stanley],
            ['Espátula metálica 4"',                  'Herramientas Manuales',   1800,  42, 'unidad', 10, 4, $central],
            ['Pincel de albañil 4"',                  'Herramientas Manuales',   2200,  40, 'unidad', 10, 4, $central],
            ['Cúter profesional 18mm',                'Herramientas Manuales',   1500,  45, 'unidad', 12, 5, $central],

            // ── HERRAMIENTAS ELÉCTRICAS ──────────────────────────────────────
            ['Taladro percutor BLACK+DECKER 650W',    'Herramientas Eléctricas', 38000, 25, 'unidad', 4,  1, $stanley],
            ['Amoladora angular 4.5" 850W',           'Herramientas Eléctricas', 32000, 25, 'unidad', 4,  1, $stanley],
            ['Caladora eléctrica 400W',               'Herramientas Eléctricas', 28000, 25, 'unidad', 3,  1, $stanley],
            ['Atornillador inalámbrico 12V',          'Herramientas Eléctricas', 22000, 28, 'unidad', 3,  1, $stanley],
            ['Rotomartillo SDS+ 800W',                'Herramientas Eléctricas', 52000, 22, 'unidad', 2,  1, $stanley],
            ['Lijadora orbital 180W',                 'Herramientas Eléctricas', 18500, 28, 'unidad', 3,  1, $stanley],
            ['Soldadora inverter 140A',               'Herramientas Eléctricas', 65000, 20, 'unidad', 2,  1, $central],
            ['Disco de corte metal 115mm',            'Herramientas Eléctricas',  1200, 42, 'unidad', 25, 10, $central],
            ['Disco de desbaste 115mm',               'Herramientas Eléctricas',  1500, 40, 'unidad', 20, 8, $central],
            ['Broca mampostería 8mm',                 'Herramientas Eléctricas',   950, 45, 'unidad', 15, 6, $central],
            ['Broca mampostería 10mm',                'Herramientas Eléctricas',  1200, 45, 'unidad', 15, 6, $central],
            ['Broca acero HSS 8mm',                   'Herramientas Eléctricas',  1100, 42, 'unidad', 12, 5, $central],
            ['Set brocas mampostería 5 piezas',       'Herramientas Eléctricas',  4800, 35, 'unidad', 6,  2, $central],

            // ── TORNILLERÍA Y FIJACIONES ─────────────────────────────────────
            ['Tornillos autorroscantes 6x1" (caja 200u)', 'Tornillería y Fijaciones', 1800, 42, 'caja',   20, 8,  $central],
            ['Tornillos autorroscantes 6x2" (caja 200u)', 'Tornillería y Fijaciones', 2100, 42, 'caja',   18, 8,  $central],
            ['Tornillos madera 4x30mm (caja 100u)',   'Tornillería y Fijaciones',  1400, 42, 'caja',   20, 8,  $central],
            ['Tornillos madera 5x50mm (caja 100u)',   'Tornillería y Fijaciones',  1800, 40, 'caja',   15, 6,  $central],
            ['Clavos de 2" (kg)',                     'Tornillería y Fijaciones',  1800, 40, 'kg',     20, 8,  $central],
            ['Clavos de 3" (kg)',                     'Tornillería y Fijaciones',  2000, 40, 'kg',     20, 8,  $central],
            ['Clavos de 4" (kg)',                     'Tornillería y Fijaciones',  2200, 38, 'kg',     15, 6,  $central],
            ['Bulones hex M8x30 (bolsa 25u)',         'Tornillería y Fijaciones',  2200, 38, 'unidad', 15, 5,  $central],
            ['Bulones hex M10x40 (bolsa 10u)',        'Tornillería y Fijaciones',  2800, 35, 'unidad', 12, 4,  $central],
            ['Tuercas M8 (bolsa 50u)',                'Tornillería y Fijaciones',   900, 45, 'unidad', 20, 8,  $central],
            ['Tuercas M10 (bolsa 25u)',               'Tornillería y Fijaciones',  1100, 42, 'unidad', 15, 6,  $central],
            ['Arandelas planas M8 (bolsa 50u)',       'Tornillería y Fijaciones',   700, 50, 'unidad', 20, 8,  $central],
            ['Tarugos plásticos 6mm (bolsa 100u)',    'Tornillería y Fijaciones',  1200, 45, 'unidad', 25, 10, $central],
            ['Tarugos plásticos 8mm (bolsa 50u)',     'Tornillería y Fijaciones',  1100, 45, 'unidad', 20, 8,  $central],
            ['Grampas para cable (caja 100u)',        'Tornillería y Fijaciones',   850, 50, 'caja',   15, 6,  $cambre],

            // ── PLOMERÍA ─────────────────────────────────────────────────────
            ['Caño PVC 4" de desagüe (metro)',        'Plomería',  1800, 32, 'metro',   25, 10, $plastik],
            ['Caño PVC 3" de desagüe (metro)',        'Plomería',  1200, 32, 'metro',   20, 8,  $plastik],
            ['Caño PVC presión 1/2" (metro)',         'Plomería',   650, 35, 'metro',   30, 12, $plastik],
            ['Caño PVC presión 3/4" (metro)',         'Plomería',   950, 32, 'metro',   25, 10, $plastik],
            ['Codo PVC 4" 90°',                      'Plomería',   900, 38, 'unidad',  20, 8,  $plastik],
            ['Codo PVC 3" 90°',                      'Plomería',   650, 40, 'unidad',  20, 8,  $plastik],
            ['Codo PVC 1/2" 90°',                    'Plomería',   280, 45, 'unidad',  30, 12, $plastik],
            ['Te PVC 4"',                             'Plomería',  1400, 35, 'unidad',  15, 6,  $plastik],
            ['Te PVC 3"',                             'Plomería',   950, 38, 'unidad',  15, 6,  $plastik],
            ['Unión PVC 4"',                         'Plomería',   650, 40, 'unidad',  20, 8,  $plastik],
            ['Llave de paso 1/2" bronce',            'Plomería',  4200, 35, 'unidad',  10, 4,  $plastik],
            ['Llave de paso 3/4" bronce',            'Plomería',  5500, 32, 'unidad',  8,  3,  $plastik],
            ['Canilla jardín 1/2" bronce',           'Plomería',  5800, 30, 'unidad',  6,  2,  $plastik],
            ['Teflon rollo 12mm x 10m',              'Plomería',   350, 55, 'rollo',   40, 15, $plastik],
            ['Pegamento PVC 250cc',                  'Plomería',  2200, 40, 'unidad',  10, 4,  $plastik],
            ['Flux pasta soldadora 100g',            'Plomería',  1800, 42, 'unidad',  8,  3,  $plastik],
            ['Cañería corrugada 25mm (metro)',        'Plomería',   480, 40, 'metro',   30, 12, $plastik],

            // ── ELECTRICIDAD ─────────────────────────────────────────────────
            ['Cable unipolar 1.5mm² (metro)',         'Electricidad',  520, 28, 'metro',   50, 20, $cambre],
            ['Cable unipolar 2.5mm² (metro)',         'Electricidad',  920, 28, 'metro',   50, 20, $cambre],
            ['Cable unipolar 4mm² (metro)',           'Electricidad', 1450, 25, 'metro',   40, 15, $cambre],
            ['Cable unipolar 6mm² (metro)',           'Electricidad', 2200, 25, 'metro',   30, 12, $cambre],
            ['Cable unipolar 10mm² (metro)',          'Electricidad', 3600, 22, 'metro',   20, 8,  $cambre],
            ['Cable bipolar 2x1.5mm² (metro)',        'Electricidad',  780, 28, 'metro',   40, 15, $cambre],
            ['Cable bipolar 2x2.5mm² (metro)',        'Electricidad', 1350, 28, 'metro',   35, 12, $cambre],
            ['Cable manguera 3x2.5mm² (metro)',       'Electricidad', 2100, 25, 'metro',   25, 10, $cambre],
            ['Interruptor simple CAMBRE',             'Electricidad', 1400, 32, 'unidad',  20, 8,  $cambre],
            ['Interruptor doble CAMBRE',              'Electricidad', 2200, 32, 'unidad',  15, 6,  $cambre],
            ['Toma doble CAMBRE',                    'Electricidad', 1700, 32, 'unidad',  20, 8,  $cambre],
            ['Toma schuko CAMBRE',                   'Electricidad', 2400, 30, 'unidad',  15, 6,  $cambre],
            ['Placa ciega CAMBRE',                   'Electricidad', 1200, 35, 'unidad',  15, 6,  $cambre],
            ['Caja rectangular plástica',            'Electricidad',  480, 42, 'unidad',  30, 12, $cambre],
            ['Disyuntor unipolar 16A',               'Electricidad', 3200, 32, 'unidad',  15, 5,  $cambre],
            ['Disyuntor unipolar 20A',               'Electricidad', 3500, 30, 'unidad',  15, 5,  $cambre],
            ['Disyuntor bipolar 32A',                'Electricidad', 7800, 28, 'unidad',  8,  3,  $cambre],
            ['Llave termomagnética 2x40A',           'Electricidad', 9500, 25, 'unidad',  6,  2,  $cambre],
            ['Tablero metálico 6 bocas',             'Electricidad', 9500, 25, 'unidad',  4,  1,  $cambre],
            ['Tablero metálico 12 bocas',            'Electricidad',14500, 22, 'unidad',  3,  1,  $cambre],
            ['Cinta aisladora 20m',                  'Electricidad',  650, 48, 'unidad',  35, 12, $cambre],
            ['Lámpara LED 9W fría',                  'Electricidad', 2100, 38, 'unidad',  25, 10, $cambre],
            ['Lámpara LED 12W fría',                 'Electricidad', 2600, 35, 'unidad',  20, 8,  $cambre],
            ['Lámpara LED 18W cálida',               'Electricidad', 3200, 32, 'unidad',  15, 6,  $cambre],
            ['Tubular LED 18W 120cm',                'Electricidad', 3600, 30, 'unidad',  12, 5,  $cambre],
            ['Foco dicroico LED 7W GU10',            'Electricidad', 1800, 38, 'unidad',  20, 8,  $cambre],
            ['Canaleta plástica 40x25mm (metro)',    'Electricidad',  680, 40, 'metro',   25, 10, $cambre],
            ['Cañería PVC rígida 20mm (metro)',      'Electricidad',  420, 40, 'metro',   30, 12, $cambre],

            // ── PINTURAS Y ACCESORIOS ────────────────────────────────────────
            ['Pintura látex interior blanca 4L',     'Pinturas y Accesorios', 12000, 30, 'litro',  8,  3, $central],
            ['Pintura látex exterior blanca 4L',     'Pinturas y Accesorios', 15000, 28, 'litro',  6,  2, $central],
            ['Pintura látex interior color 4L',      'Pinturas y Accesorios', 13500, 28, 'litro',  5,  2, $central],
            ['Esmalte sintético blanco 1L',          'Pinturas y Accesorios',  7500, 30, 'litro',  8,  3, $central],
            ['Esmalte sintético negro 1L',           'Pinturas y Accesorios',  7500, 30, 'litro',  6,  2, $central],
            ['Membrana líquida 5kg',                 'Pinturas y Accesorios', 17500, 28, 'kg',     5,  2, $central],
            ['Rodillo lana 23cm',                    'Pinturas y Accesorios',  2200, 38, 'unidad', 10, 4, $central],
            ['Rodillo lana 9cm',                     'Pinturas y Accesorios',  1400, 40, 'unidad', 10, 4, $central],
            ['Bandeja para rodillo plástica',        'Pinturas y Accesorios',  1800, 40, 'unidad', 8,  3, $central],
            ['Brocha 4"',                            'Pinturas y Accesorios',  1200, 42, 'unidad', 12, 5, $central],
            ['Brocha 2"',                            'Pinturas y Accesorios',   800, 45, 'unidad', 12, 5, $central],
            ['Lija al agua grano 180',               'Pinturas y Accesorios',   500, 48, 'unidad', 20, 8, $central],
            ['Lija al agua grano 220',               'Pinturas y Accesorios',   500, 48, 'unidad', 20, 8, $central],
            ['Lija madera grano 80',                 'Pinturas y Accesorios',   450, 50, 'unidad', 20, 8, $central],
            ['Masilla plástica 1kg',                 'Pinturas y Accesorios',  3200, 35, 'kg',     8,  3, $central],

            // ── ADHESIVOS Y SELLADORES ───────────────────────────────────────
            ['Sellador siliconado transparente 280ml', 'Adhesivos y Selladores', 2500, 38, 'unidad', 12, 5, $central],
            ['Sellador siliconado blanco 280ml',     'Adhesivos y Selladores',  2500, 38, 'unidad', 12, 5, $central],
            ['Sellador siliconado negro 280ml',      'Adhesivos y Selladores',  2500, 38, 'unidad', 8,  3, $central],
            ['Adhesivo epoxi 2 partes 24ml',         'Adhesivos y Selladores',  3500, 38, 'unidad', 10, 4, $central],
            ['Superglue gel 3g',                     'Adhesivos y Selladores',   950, 55, 'unidad', 20, 8, $central],
            ['Cinta doble faz 18mm x 5m',            'Adhesivos y Selladores',  1400, 42, 'unidad', 15, 6, $central],
            ['Cinta de papel 24mm x 50m',            'Adhesivos y Selladores',  1100, 45, 'unidad', 12, 5, $central],
            ['Espuma expansiva 500ml',               'Adhesivos y Selladores',  4200, 32, 'unidad', 8,  3, $central],
            ['Cemento de contacto 500cc',            'Adhesivos y Selladores',  5500, 30, 'unidad', 6,  2, $central],

            // ── ACEITES Y LUBRICANTES ────────────────────────────────────────
            ['Aceite de motor 20W50 mineral 1L',     'Aceites y Lubricantes',  5200, 32, 'litro',  20, 8,  $lubri],
            ['Aceite de motor 20W50 mineral 4L',     'Aceites y Lubricantes', 18000, 30, 'litro',  15, 6,  $lubri],
            ['Aceite de motor sintético 5W30 1L',    'Aceites y Lubricantes',  7800, 28, 'litro',  12, 5,  $lubri],
            ['Aceite de motor sintético 5W40 1L',    'Aceites y Lubricantes',  8200, 28, 'litro',  10, 4,  $lubri],
            ['Aceite de motor sintético 5W40 4L',    'Aceites y Lubricantes', 28000, 25, 'litro',  8,  3,  $lubri],
            ['Aceite de motor diesel 15W40 1L',      'Aceites y Lubricantes',  5800, 30, 'litro',  12, 5,  $lubri],
            ['Aceite de motor diesel 15W40 4L',      'Aceites y Lubricantes', 20000, 28, 'litro',  8,  3,  $lubri],
            ['Aceite para cajas 80W90 1L',           'Aceites y Lubricantes',  4600, 32, 'litro',  10, 4,  $lubri],
            ['Aceite para dirección hidráulica 1L',  'Aceites y Lubricantes',  5500, 30, 'litro',  8,  3,  $lubri],
            ['Grasa multipropósito 500g',            'Aceites y Lubricantes',  3500, 35, 'kg',     10, 4,  $lubri],
            ['Grasa para rodamiento 250g',           'Aceites y Lubricantes',  2800, 35, 'kg',     8,  3,  $lubri],
            ['Lubricante WD-40 360ml',               'Aceites y Lubricantes',  4000, 32, 'unidad', 10, 4,  $lubri],
            ['Lubricante WD-40 500ml',               'Aceites y Lubricantes',  5500, 30, 'unidad', 8,  3,  $lubri],
            ['Refrigerante anticongelante rosa 1L',  'Aceites y Lubricantes',  3800, 32, 'litro',  12, 5,  $lubri],
            ['Refrigerante anticongelante rosa 4L',  'Aceites y Lubricantes', 13500, 28, 'litro',  8,  3,  $lubri],
            ['Líquido para frenos DOT4 500ml',       'Aceites y Lubricantes',  4200, 30, 'litro',  8,  3,  $lubri],
            ['Aditivo limpia inyectores 200ml',      'Aceites y Lubricantes',  3500, 35, 'unidad', 6,  2,  $lubri],
            ['Desengrasante multiusos 500ml',        'Aceites y Lubricantes',  2500, 38, 'unidad', 10, 4,  $lubri],
            ['Filtro de aceite universal spin-on',   'Aceites y Lubricantes',  2900, 35, 'unidad', 8,  3,  $lubri],

            // ── SEGURIDAD E EPP ──────────────────────────────────────────────
            ['Casco de obra amarillo',               'Seguridad e EPP',  4200, 35, 'unidad', 8,  3, $central],
            ['Guantes de cuero (par)',                'Seguridad e EPP',  3200, 38, 'par',    10, 4, $central],
            ['Guantes de nitrilo (caja 100u)',        'Seguridad e EPP',  6500, 32, 'caja',   6,  2, $central],
            ['Guantes de goma (par)',                 'Seguridad e EPP',  2500, 40, 'par',    8,  3, $central],
            ['Anteojos de seguridad',                'Seguridad e EPP',  2400, 38, 'unidad', 10, 4, $central],
            ['Protector facial (careta)',             'Seguridad e EPP',  5800, 30, 'unidad', 4,  1, $central],
            ['Tapones auditivos (par)',               'Seguridad e EPP',  1400, 45, 'par',    15, 6, $central],
            ['Arnés de seguridad',                   'Seguridad e EPP', 18500, 25, 'unidad', 2,  1, $central],
            ['Máscara con filtro para polvo',        'Seguridad e EPP',  3800, 35, 'unidad', 8,  3, $central],
        ];

        $categoryCache = [];

        foreach ($products as $item) {
            [$name, $catName, $cost, $margin, $unit, $stock, $minStock, $supplier] = $item;

            // Crear categoría si no existe
            if (! isset($categoryCache[$catName])) {
                $categoryCache[$catName] = Category::firstOrCreate(
                    ['slug' => Str::slug($catName)],
                    ['name' => $catName, 'active' => true]
                );
            }

            $salePrice = round($cost * (1 + $margin / 100), 2);

            Product::firstOrCreate(
                ['name' => $name],
                [
                    'category_id'        => $categoryCache[$catName]->id,
                    'supplier_id'        => $supplier?->id,
                    'sale_price'         => $salePrice,
                    'cost_price'         => $cost,
                    'margin_percentage'  => $margin,
                    'unit'               => $unit,
                    'stock'              => $stock,
                    'min_stock'          => $minStock,
                    'active'             => true,
                ]
            );
        }
    }
}
