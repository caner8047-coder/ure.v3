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
        Schema::create('production_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete(); // UrunIDNo
            $table->string('task_start_date')->nullable(); // GorevBaslangicTarihi (in C# it is nvarchar)
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete(); // BolumAdiNo
            $table->foreignId('component_id')->nullable()->constrained('components')->cascadeOnDelete(); // AraUrunAdiNo
            $table->integer('quantity')->default(0); // Adet
            $table->integer('total_quantity')->default(0); // ToplamAdet
            $table->integer('step_order')->default(0); // AdimSirasi (V2 eklentisi)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_pools');
    }
};
