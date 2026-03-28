<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // AraUrunAdi
            $table->integer('performance')->default(0); // Performans
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete(); // BolumAdiNo
            $table->integer('min_quantity')->default(0); // MinAdet
            $table->string('category')->nullable(); // UrunCesidi
            $table->text('path')->nullable(); // Yol (BOM relationship string e.g. "No-Adet:No-Adet")
            $table->string('image')->nullable(); // Resim
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
