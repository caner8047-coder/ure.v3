<?php

namespace Tests\Feature;

use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class WorkOrderCenterBackfillCommandTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_backfill_command_moves_legacy_history_into_center_tables(): void
    {
        $this->createWorkOrderCenterTables();
        $this->createLegacyOrdersTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 10,
            'SiparisNo' => 'S-9001',
            'Musteri' => 'Test Musteri',
            'UrunAdi' => 'Puf Test',
            'Adet' => 2,
            'Kategori' => 'Puf',
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'GorevNo' => 501,
            'IsEmriTarihi' => now(),
            'GuncellemeTarihi' => now(),
        ]);

        DB::table('tbGorevler')->insert([
            'No' => 501,
            'SiparisSatirNo' => 10,
            'SiparisNo' => 'S-9001',
            'ToplamAdet' => 2,
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolum')->insert([
            'No' => 3,
            'BolumAdi' => 'Dikim',
        ]);

        DB::table('tbIsEmriGecmisi')->insert([
            'No' => 1,
            'SiparisSatirNo' => 10,
            'SiparisNo' => 'S-9001',
            'Musteri' => 'Test Musteri',
            'UrunAdi' => 'Puf Test',
            'SistemUrunAdi' => 'Puf Test Sistem',
            'Adet' => 2,
            'Kategori' => 'Puf',
            'IsEmriTarihi' => now(),
            'IslemTipi' => 'IsEmriVerildi',
            'IslemTarihi' => now(),
            'GorevNo' => 501,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'KargoSonTeslim' => now(),
        ]);

        $this->artisan('work-order-center:backfill')
            ->assertExitCode(0);

        $this->assertDatabaseHas('work_order_events', [
            'event_type' => 'work_order_created_single',
            'order_item_no' => 10,
            'work_order_no' => 501,
            'source_screen' => 'Legacy Is Emri Gecmisi',
        ]);

        $this->assertDatabaseHas('work_order_snapshots', [
            'aggregate_type' => 'order_item',
            'aggregate_id' => 10,
            'current_status' => 'IsEmriVerildi',
        ]);
    }
}
