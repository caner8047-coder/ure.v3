<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbSiparisOzelUretimRezervasyon')) {
            return;
        }

        Schema::create('tbSiparisOzelUretimRezervasyon', function (Blueprint $table) {
            $table->id('No');
            $table->unsignedBigInteger('SiparisSatirNo')->index();
            $table->unsignedBigInteger('OzelUretimSatirNo')->index();
            $table->integer('Adet')->default(0);
            $table->boolean('Aktif')->default(true)->index();
            $table->dateTime('OlusturmaTarihi')->nullable();
            $table->dateTime('GuncellemeTarihi')->nullable();

            $table->index(['SiparisSatirNo', 'Aktif'], 'gied_alloc_order_active_idx');
            $table->index(['OzelUretimSatirNo', 'Aktif'], 'gied_alloc_special_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbSiparisOzelUretimRezervasyon');
    }
};
