<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Productos como manguera se venden por metro/kg/litro, no por unidad
     * entera. Estas columnas eran integer y truncaban esas cantidades.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY stock DECIMAL(12,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY min_stock DECIMAL(12,3) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE sale_items MODIFY quantity DECIMAL(12,3) NOT NULL');

        DB::statement('ALTER TABLE stock_movements MODIFY quantity DECIMAL(12,3) NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY stock_before DECIMAL(12,3) NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY stock_after DECIMAL(12,3) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products MODIFY stock INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY min_stock INT NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE sale_items MODIFY quantity INT NOT NULL');

        DB::statement('ALTER TABLE stock_movements MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY stock_before INT NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY stock_after INT NOT NULL');
    }
};
