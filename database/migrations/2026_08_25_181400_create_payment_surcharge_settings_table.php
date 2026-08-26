<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_surcharge_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('cash_percentage', 6, 2)->default(0);
            $table->decimal('debit_percentage', 6, 2)->default(0);
            $table->decimal('credit_percentage', 6, 2)->default(0);
            $table->unsignedInteger('rounding_step')->default(0);
            $table->string('rounding_mode', 16)->default('up');
            $table->timestamps();
        });

        DB::table('payment_surcharge_settings')->insert([
            'id' => 1,
            'cash_percentage' => 0,
            'debit_percentage' => 0,
            'credit_percentage' => 0,
            'rounding_step' => 0,
            'rounding_mode' => 'up',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_surcharge_settings');
    }
};
