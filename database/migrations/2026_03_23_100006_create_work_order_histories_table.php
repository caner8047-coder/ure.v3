<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete(); // SiparisSatirNo
            $table->string('order_no')->nullable(); // SiparisNo
            $table->string('customer')->nullable(); // Musteri
            $table->string('product_name')->nullable(); // UrunAdi
            $table->string('system_product_name')->nullable(); // SistemUrunAdi
            $table->integer('quantity')->nullable(); // Adet
            $table->string('category')->nullable(); // Kategori
            $table->dateTime('work_order_date')->nullable(); // IsEmriTarihi
            $table->string('operation_type'); // IslemTipi
            $table->dateTime('operation_date')->useCurrent(); // IslemTarihi
            $table->integer('task_id')->nullable(); // GorevNo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_histories');
    }
};
