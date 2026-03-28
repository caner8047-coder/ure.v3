<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_definition_id')->constrained('set_definitions')->cascadeOnDelete(); // SetNo
            $table->integer('product_id'); // UrunNo (Can be Product or Component conceptually)
            $table->integer('quantity')->default(1); // Adet
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_contents');
    }
};
