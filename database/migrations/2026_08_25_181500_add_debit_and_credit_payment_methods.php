<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'transfer', 'card', 'debit', 'credit', 'mixed') NOT NULL");
        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'card', 'debit', 'credit') NOT NULL");
    }

    public function down(): void
    {
        DB::table('sale_payments')
            ->whereIn('method', ['debit', 'credit'])
            ->update(['method' => 'card']);

        DB::table('sales')
            ->whereIn('payment_method', ['debit', 'credit'])
            ->update(['payment_method' => 'card']);

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'card') NOT NULL");
        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'transfer', 'card', 'mixed') NOT NULL");
    }
};
