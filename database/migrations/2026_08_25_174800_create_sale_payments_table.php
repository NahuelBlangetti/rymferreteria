<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'transfer', 'card']);
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['sale_id', 'method']);
        });

        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'transfer', 'card', 'mixed') NOT NULL");

        DB::table('sales')->orderBy('id')->chunkById(100, function ($sales) {
            $now = now();
            $rows = [];

            foreach ($sales as $sale) {
                if (! in_array($sale->payment_method, ['cash', 'transfer', 'card'], true)) {
                    continue;
                }

                $rows[] = [
                    'sale_id' => $sale->id,
                    'method' => $sale->payment_method,
                    'amount' => $sale->total,
                    'created_at' => $sale->created_at ?? $now,
                    'updated_at' => $sale->updated_at ?? $now,
                ];
            }

            if ($rows !== []) {
                DB::table('sale_payments')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');

        DB::table('sales')
            ->where('payment_method', 'mixed')
            ->update(['payment_method' => 'cash']);

        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'transfer', 'card') NOT NULL");
    }
};
