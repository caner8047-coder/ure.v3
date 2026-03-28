<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_stock_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('components')->cascadeOnDelete(); // AraUrunAdiNo
            $table->integer('threshold_quantity'); // EsikMiktar
            $table->integer('current_stock'); // MevcutStok
            $table->string('warning_type')->default('Uyari'); // UyariTipi
            $table->boolean('auto_work_order_created')->default(false); // OtomatikIsEmriVerildi
            $table->boolean('is_read')->default(false); // Okundu
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_stock_warnings');
    }
};
