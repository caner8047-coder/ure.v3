<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesLegacyInMemoryDatabase
{
    protected function useLegacyInMemoryDatabase(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', false);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
    }

    protected function createLegacyOrdersTable(): void
    {
        Schema::create('tbSiparisSatir', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->string('SiparisNo', 50)->nullable();
            $table->string('Pazaryeri', 50)->nullable();
            $table->string('Magaza', 100)->nullable();
            $table->dateTime('SiparisTarihi')->nullable();
            $table->string('Musteri', 200)->nullable();
            $table->string('UrunAdi', 300)->nullable();
            $table->integer('Adet')->default(0);
            $table->text('MusteriNotu')->nullable();
            $table->string('Kategori', 100)->nullable();
            $table->string('Durum', 30)->nullable();
            $table->boolean('Aktif')->default(true);
            $table->integer('EslesenUrunNo')->nullable();
            $table->string('EslesenUrunTur', 10)->nullable();
            $table->integer('EslesmePuani')->nullable();
            $table->string('EslesmeYontemi', 10)->nullable();
            $table->dateTime('IsEmriTarihi')->nullable();
            $table->integer('GorevNo')->nullable();
            $table->text('TamponDusumleri')->nullable();
            $table->integer('BagliOlduguOzelUretimNo')->nullable();
            $table->dateTime('KargoSonTeslim')->nullable();
            $table->string('StokKodu', 50)->nullable();
            $table->dateTime('YuklemeTarihi')->nullable();
            $table->dateTime('GuncellemeTarihi')->nullable();
            $table->boolean('SetMi')->default(false);
            $table->integer('SetNo')->nullable();
            $table->integer('AnaSetSatirNo')->nullable();
        });
    }

    protected function createGiedAllocationTable(): void
    {
        Schema::create('tbSiparisOzelUretimRezervasyon', function (Blueprint $table) {
            $table->increments('No');
            $table->unsignedBigInteger('SiparisSatirNo')->index();
            $table->unsignedBigInteger('OzelUretimSatirNo')->index();
            $table->integer('Adet')->default(0);
            $table->boolean('Aktif')->default(true);
            $table->dateTime('OlusturmaTarihi')->nullable();
            $table->dateTime('GuncellemeTarihi')->nullable();
        });
    }

    protected function createLegacyOrderMatchingTables(): void
    {
        Schema::create('tbUrunEslestirmeOnbellek', function (Blueprint $table) {
            $table->increments('No');
            $table->string('ExcelUrunAdi', 300)->unique();
            $table->integer('EslesenUrunNo');
            $table->string('EslesenUrunTur', 10);
            $table->dateTime('OlusturmaTarihi')->nullable();
        });

        Schema::create('tbSetTanimlari', function (Blueprint $table) {
            $table->increments('No');
            $table->string('ExcelSetAdi', 500)->unique();
            $table->string('SetAdi', 200)->nullable();
            $table->dateTime('OlusturmaTarihi')->nullable();
            $table->boolean('Aktif')->default(true);
        });

        Schema::create('tbSetIcerikleri', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('SetNo');
            $table->integer('UrunNo');
            $table->integer('Adet')->default(1);
        });
    }

    protected function createLegacyProductsTable(): void
    {
        Schema::create('tbUrunler', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->string('UrunID', 500)->nullable();
            $table->text('AraAdlarYol')->nullable();
            $table->string('SistemAdi', 500)->nullable();
            $table->string('SistemKodu', 500)->nullable();
            $table->string('Resim', 500)->nullable();
            $table->integer('MergedIntoNo')->nullable();
            $table->dateTime('MergedAt')->nullable();
        });
    }

    protected function createLegacyComponentsTable(): void
    {
        Schema::create('tbAraUrun', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->string('AraUrunAdi', 500)->nullable();
            $table->integer('Performans')->nullable()->default(0);
            $table->integer('BolumAdiNo')->nullable();
            $table->integer('MinAdet')->nullable()->default(0);
            $table->string('UrunCesidi', 100)->nullable();
            $table->text('Yol')->nullable();
            $table->string('Resim', 500)->nullable();
            $table->integer('MergedIntoNo')->nullable();
            $table->dateTime('MergedAt')->nullable();
        });
    }

    protected function createLegacyStocksTable(): void
    {
        Schema::create('tbBolumAraStok', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('BolumAdiNo')->nullable();
            $table->integer('Adet')->nullable()->default(0);
            $table->integer('AraUrunAdiNo')->nullable();
            $table->integer('UrunIDNo')->nullable();
            $table->integer('TamponMiktar')->nullable()->default(0);
        });
    }

    protected function createStockMovementsTable(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('movement_uuid')->unique();
            $table->unsignedBigInteger('stock_row_no')->nullable();
            $table->unsignedBigInteger('component_no')->nullable();
            $table->unsignedBigInteger('department_no')->nullable();
            $table->unsignedBigInteger('product_no')->nullable();
            $table->string('movement_type', 60);
            $table->string('direction', 20);
            $table->string('title_human', 255);
            $table->integer('quantity_before')->nullable();
            $table->integer('quantity_delta')->default(0);
            $table->integer('quantity_after')->nullable();
            $table->integer('buffer_before')->nullable();
            $table->integer('buffer_delta')->default(0);
            $table->integer('buffer_after')->nullable();
            $table->string('source_type', 60)->nullable();
            $table->string('source_id', 150)->nullable();
            $table->unsignedBigInteger('order_item_no')->nullable();
            $table->string('order_no', 50)->nullable();
            $table->unsignedBigInteger('work_order_no')->nullable();
            $table->unsignedBigInteger('pool_no')->nullable();
            $table->unsignedBigInteger('personnel_task_no')->nullable();
            $table->string('source_screen', 100)->nullable();
            $table->string('source_action', 100)->nullable();
            $table->string('source_route', 200)->nullable();
            $table->string('actor_type', 30)->default('system');
            $table->string('actor_id', 50)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_department', 150)->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('happened_at');
            $table->timestamps();
        });
    }

    protected function createLegacyDepartmentsTable(): void
    {
        Schema::create('tbBolum', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->string('BolumAdi', 150)->nullable();
        });
    }

    protected function createLegacyPersonnelTable(): void
    {
        Schema::create('tbPersonel', function (Blueprint $table) {
            $table->integer('PersonelNo')->primary();
            $table->string('Ad', 100)->nullable();
            $table->string('Soyad', 100)->nullable();
            $table->integer('BolumAdiNo')->nullable();
            $table->string('Mail', 150)->nullable();
            $table->string('Telefon', 50)->nullable();
            $table->string('Adres', 255)->nullable();
            $table->string('Sifre', 255)->nullable();
        });
    }

    protected function createLegacyMessagesTable(): void
    {
        Schema::create('tbIletisim', function (Blueprint $table) {
            $table->increments('MesajNo');
            $table->integer('PersonelNo')->nullable();
            $table->integer('BolumAdiNo')->nullable();
            $table->text('Mesaj')->nullable();
            $table->string('Tarih', 50)->nullable();
            $table->string('Saat', 20)->nullable();
            $table->string('Mail', 150)->nullable();
            $table->string('AdSoyad', 200)->nullable();
            $table->boolean('Okundu')->default(false);
        });
    }

    protected function createCriticalStockTables(): void
    {
        Schema::create('tbKritikStokEsik', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('AraUrunAdiNo');
            $table->integer('EsikMiktar')->default(0);
            $table->boolean('OtomatikIsEmri')->default(false);
            $table->integer('IsEmriAdet')->nullable();
            $table->integer('UrunIDNo')->nullable();
            $table->boolean('Aktif')->default(true);
            $table->dateTime('SonKontrolTarihi')->nullable();
            $table->dateTime('SonUyariTarihi')->nullable();
            $table->dateTime('OlusturmaTarihi')->nullable();
        });

        Schema::create('tbKritikStokUyari', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('AraUrunAdiNo');
            $table->integer('EsikMiktar')->default(0);
            $table->integer('MevcutStok')->default(0);
            $table->string('UyariTipi', 30)->nullable();
            $table->boolean('OtomatikIsEmriVerildi')->default(false);
            $table->dateTime('OlusturmaTarihi')->nullable();
        });
    }

    protected function createLegacyPoolTable(): void
    {
        Schema::create('tbBolumHavuz', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('UrunIDNo')->nullable();
            $table->string('GorevBaslangicTarihi', 50)->nullable();
            $table->integer('BolumAdiNo')->nullable();
            $table->text('Aciklama')->nullable();
            $table->string('GorevBaslangicSaati', 50)->nullable();
            $table->integer('AraUrunAdiNo')->nullable();
            $table->integer('Adet')->nullable()->default(0);
            $table->integer('ToplamAdet')->nullable()->default(0);
            $table->integer('AdimSirasi')->nullable()->default(0);
            $table->integer('SiparisSatirNo')->nullable();
            $table->string('SiparisNo', 50)->nullable();
        });
    }

    protected function createLegacyPersonnelTasksTable(): void
    {
        Schema::create('tbPersonelGorev', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('UrunIDNo')->nullable();
            $table->integer('PersonelNo')->nullable();
            $table->string('GorevBaslamaTarihi', 50)->nullable();
            $table->integer('Adet')->default(0);
            $table->integer('BekleyenAdet')->default(0);
            $table->string('Onay', 10)->nullable();
            $table->integer('AraUrunAdiNo')->nullable();
            $table->integer('BolumAdiNo')->nullable();
            $table->integer('SiparisSatirNo')->nullable();
            $table->string('SiparisNo', 50)->nullable();
        });
    }

    protected function createLegacyIssuedTasksTable(bool $withPersonnelNo = false): void
    {
        Schema::create('tbVerilenGorevler', function (Blueprint $table) use ($withPersonnelNo) {
            $table->increments('No');
            $table->integer('UrunIDNo')->nullable();
            $table->dateTime('GorevTarihi')->nullable();
            $table->integer('ToplamAdet')->nullable()->default(0);
            $table->text('Aciklama')->nullable();
            if ($withPersonnelNo) {
                $table->integer('PersonelNo')->nullable();
            }
        });
    }

    protected function createLegacyWorkOrdersTable(): void
    {
        Schema::create('tbGorevler', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->integer('UrunIDNo')->nullable();
            $table->integer('SiparisSatirNo')->nullable();
            $table->string('SiparisNo', 50)->nullable();
            $table->string('GorevBaslamaTarihi', 50)->nullable();
            $table->string('GorevBitisTarihi', 50)->nullable();
            $table->integer('ToplamAdet')->default(0);
            $table->integer('BolumAdiNo')->nullable();
            $table->integer('PersonelNo')->nullable();
            $table->integer('Performans')->nullable()->default(0);
            $table->integer('AraUrunAdiNo')->nullable();
        });
    }

    protected function createLegacyWorkOrderHistoryTable(): void
    {
        Schema::create('tbIsEmriGecmisi', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('SiparisSatirNo');
            $table->string('SiparisNo', 50)->nullable();
            $table->string('Musteri', 200)->nullable();
            $table->string('UrunAdi', 300)->nullable();
            $table->string('SistemUrunAdi', 300)->nullable();
            $table->integer('Adet')->nullable();
            $table->string('Kategori', 100)->nullable();
            $table->dateTime('IsEmriTarihi')->nullable();
            $table->string('IslemTipi', 30);
            $table->dateTime('IslemTarihi');
            $table->integer('GorevNo')->nullable();
            $table->integer('EslesenUrunNo')->nullable();
            $table->string('EslesenUrunTur', 10)->nullable();
            $table->dateTime('KargoSonTeslim')->nullable();
        });
    }

    protected function createWorkOrderCenterTables(): void
    {
        Schema::create('work_order_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->uuid('correlation_id');
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('order_item_no')->nullable();
            $table->string('order_no', 50)->nullable();
            $table->unsignedBigInteger('work_order_no')->nullable();
            $table->unsignedBigInteger('pool_no')->nullable();
            $table->unsignedBigInteger('personnel_task_no')->nullable();
            $table->unsignedBigInteger('special_production_no')->nullable();
            $table->string('event_type', 100);
            $table->string('event_group', 50);
            $table->string('source_screen', 100)->nullable();
            $table->string('source_action', 100)->nullable();
            $table->string('source_route', 200)->nullable();
            $table->string('actor_type', 30)->default('system');
            $table->string('actor_id', 50)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_department', 150)->nullable();
            $table->string('status_before', 50)->nullable();
            $table->string('status_after', 50)->nullable();
            $table->string('title_human', 255);
            $table->text('summary_human');
            $table->text('next_step_human')->nullable();
            $table->json('payload_before')->nullable();
            $table->json('payload_after')->nullable();
            $table->json('context')->nullable();
            $table->dateTime('happened_at');
            $table->timestamps();
        });

        Schema::create('work_order_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('order_item_no')->nullable();
            $table->string('order_no', 50)->nullable();
            $table->unsignedBigInteger('work_order_no')->nullable();
            $table->string('current_status', 50)->nullable();
            $table->string('current_stage', 50)->nullable();
            $table->string('current_holder_type', 50)->nullable();
            $table->string('current_holder_id', 50)->nullable();
            $table->string('current_holder_name', 150)->nullable();
            $table->unsignedBigInteger('linked_special_production_no')->nullable();
            $table->string('next_expected_action', 255)->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->dateTime('last_changed_at')->nullable();
            $table->integer('alert_count')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }
}
