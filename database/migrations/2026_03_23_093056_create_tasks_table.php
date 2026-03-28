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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete(); // UrunIDNo
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete(); // PersonelNo
            $table->integer('quantity')->default(0); // Adet
            $table->integer('pending_quantity')->default(0); // BekleyenAdet
            $table->string('approval')->nullable(); // Onay
            $table->foreignId('component_id')->nullable()->constrained('components')->cascadeOnDelete(); // AraUrunAdiNo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
