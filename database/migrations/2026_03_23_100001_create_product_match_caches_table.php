<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_match_caches', function (Blueprint $table) {
            $table->id();
            $table->string('excel_product_name')->unique(); // ExcelUrunAdi
            $table->integer('matched_product_id'); // EslesenUrunNo
            $table->string('matched_product_type'); // EslesenUrunTur
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_match_caches');
    }
};
