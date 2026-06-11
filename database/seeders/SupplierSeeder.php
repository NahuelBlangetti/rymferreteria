<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name'           => 'Distribuidora Cambre S.A.',
                'cuit'           => '30-58423110-7',
                'phone'          => '0800-222-2627',
                'email'          => 'ventas@cambre.com.ar',
                'contact_person' => 'Martín Rodríguez',
                'payment_terms'  => '30_dias',
                'notes'          => 'Proveedor principal de materiales eléctricos. Lista de precios mensual.',
                'active'         => true,
            ],
            [
                'name'           => 'Ferretería Central S.R.L.',
                'cuit'           => '30-71234567-4',
                'phone'          => '(011) 4567-8900',
                'email'          => 'pedidos@ferreteriacentral.com.ar',
                'contact_person' => 'Roberto Díaz',
                'payment_terms'  => '30_dias',
                'notes'          => 'Herramientas, tornillería y productos generales de ferretería.',
                'active'         => true,
            ],
            [
                'name'           => 'Lubricantes del Sur S.A.',
                'cuit'           => '30-69812345-1',
                'phone'          => '(0291) 455-7800',
                'email'          => 'ventas@lubricantesdelsur.com.ar',
                'contact_person' => 'Pablo Sánchez',
                'payment_terms'  => 'contado',
                'notes'          => 'Aceites, lubricantes y fluidos para el lubricentro. Entrega semanal.',
                'active'         => true,
            ],
            [
                'name'           => 'Distribuidora Stanley Argentina',
                'cuit'           => '30-52345678-2',
                'phone'          => '(011) 5263-4000',
                'email'          => 'distribuidores@stanleyargentina.com.ar',
                'contact_person' => 'Laura Fernández',
                'payment_terms'  => '15_dias',
                'notes'          => 'Herramientas manuales y eléctricas Stanley y Black+Decker.',
                'active'         => true,
            ],
            [
                'name'           => 'Plastik S.A. - Plomería',
                'cuit'           => '30-61234512-9',
                'phone'          => '(011) 4890-1234',
                'email'          => 'ventas@plastik.com.ar',
                'contact_person' => 'Carlos Méndez',
                'payment_terms'  => '30_dias',
                'notes'          => 'Caños, accesorios de plomería y sanitarios.',
                'active'         => true,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(['cuit' => $data['cuit']], $data);
        }
    }
}
