<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_stock_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->unique()->constrained('components')->cascadeOnDelete(); // AraUrunAdiNo
            $table->integer('threshold_quantity')->default(5); // EsikMiktar
            $table->boolean('auto_work_order')->default(false); // OtomatikIsEmri
            $table->integer('work_order_quantity')->nullable()->default(10); // IsEmriAdet
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete(); // UrunIDNo
            $table->boolean('is_active')->default(true); // Aktif
            $table->dateTime('last_check_date')->nullable(); // SonKontrolTarihi
            $table->dateTime('last_warning_date')->nullable(); // SonUyariTarihi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_stock_thresholds');
    }
};
