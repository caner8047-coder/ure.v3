<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('excel_set_name')->unique(); // ExcelSetAdi
            $table->string('set_name')->nullable(); // SetAdi
            $table->boolean('is_active')->default(true); // Aktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_definitions');
    }
};
