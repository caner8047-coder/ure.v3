<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_no'); // SiparisNo
            $table->string('marketplace')->nullable(); // Pazaryeri
            $table->string('store')->nullable(); // Magaza
            $table->dateTime('order_date')->nullable(); // SiparisTarihi
            $table->string('customer')->nullable(); // Musteri
            $table->string('product_name'); // UrunAdi
            $table->integer('quantity')->default(1); // Adet
            $table->text('customer_note')->nullable(); // MusteriNotu
            $table->dateTime('deadline')->nullable(); // KargoSonTeslim
            $table->string('category')->nullable(); // Kategori
            $table->string('status')->default('UretimBekliyor'); // Durum
            $table->boolean('is_active')->default(true); // Aktif
            $table->integer('matched_product_id')->nullable(); // EslesenUrunNo
            $table->string('matched_product_type')->nullable(); // EslesenUrunTur
            $table->integer('match_score')->nullable(); // EslesmePuani
            $table->string('match_method')->nullable(); // EslesmeYontemi
            $table->dateTime('work_order_date')->nullable(); // IsEmriTarihi
            $table->integer('task_id')->nullable(); // GorevNo
            $table->string('stock_code')->nullable(); // StokKodu
            $table->boolean('is_set')->default(false); // SetMi
            $table->integer('set_id')->nullable(); // SetNo
            $table->integer('master_set_item_id')->nullable(); // AnaSetSatirNo
            $table->text('buffer_deductions')->nullable(); // TamponDusumleri
            $table->integer('linked_special_production_id')->nullable(); // BagliOlduguOzelUretimNo
            $table->timestamps(); // Matches YuklemeTarihi / GuncellemeTarihi
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
