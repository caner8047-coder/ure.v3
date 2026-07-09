<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BomService;
use App\Services\OrderSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tests\Support\UsesLegacyInMemoryDatabase;

class OrderManagementRegressionTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_order_status_filter_and_stats_use_same_normal_order_scope(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-WAIT',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Bekleyen Siparis',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-WO',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Is Emri Verilen Siparis',
                'Adet' => 1,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
            ],
            [
                'No' => 3,
                'SiparisNo' => 'OZEL-WO',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Ozel Uretim',
                'Adet' => 1,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
            ],
            [
                'No' => 4,
                'SiparisNo' => 'S-INACTIVE-WO',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Inaktif Is Emri',
                'Adet' => 1,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 0,
            ],
        ]);

        $normalResponse = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=IsEmriVerildi');

        $normalResponse
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 2)
            ->assertJsonPath('stats.toplam', 2)
            ->assertJsonPath('stats.uretimBekliyor', 1)
            ->assertJsonPath('stats.isEmriVerildi', 1);

        $specialResponse = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=IsEmriVerildi&ozelUretim=1');

        $specialResponse
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 3)
            ->assertJsonPath('stats.toplam', 1)
            ->assertJsonPath('stats.isEmriVerildi', 1);
    }

    public function test_get_orders_counts_hammadde_work_order_assignment_progress(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbAraUrun')->insert([
            'No' => 3151,
            'AraUrunAdi' => 'MAR/legna kose takimi kasa kavak kesim',
            'UrunCesidi' => 'Ham Madde',
            'BolumAdiNo' => 4,
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 3151,
            'UrunIDNo' => 50,
            'Adet' => 12,
            'TamponMiktar' => 0,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 310,
            'SiparisNo' => 'STOK-20260515-5244',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'MAR/legna kose takimi kasa kavak kesim',
            'Adet' => 30,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 3151,
            'EslesenUrunTur' => 'HamMadde',
            'IsEmriTarihi' => '2026-05-15 00:12:19',
            'GorevNo' => 115,
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 329,
            'UrunIDNo' => 50,
            'SiparisSatirNo' => 310,
            'SiparisNo' => 'STOK-20260515-5244',
            'PersonelNo' => 52,
            'GorevBaslamaTarihi' => '18/05/2026 00:00',
            'Adet' => 30,
            'BekleyenAdet' => 0,
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 3151,
            'Onay' => 'hazir',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=IsEmriVerildi&ozelUretim=1');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 310)
            ->assertJsonPath('orders.0.eslesenUrunTur', 'HamMadde')
            ->assertJsonPath('orders.0.stokAdet', 12)
            ->assertJsonPath('orders.0.havuzAdet', 0)
            ->assertJsonPath('orders.0.personelAdet', 30)
            ->assertJsonPath('orders.0.uretimdeAdet', 30);
    }

    public function test_get_orders_uses_order_trace_before_global_product_assignment_totals(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbUrunler')->insert([
            'No' => 3922,
            'UrunID' => 'sehpa zem alaves(kahve pinotex)',
            'SistemAdi' => 'Alaves sehpa',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 2945,
            'AraUrunAdi' => 'sehpa zem alaves(kahve pinotex)',
            'UrunCesidi' => 'Nihayi Ürün',
            'BolumAdiNo' => 1023,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 214,
                'SiparisNo' => 'STOK-20260514-8852',
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => 'sehpa zem alaves(kahve pinotex)',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 3922,
                'EslesenUrunTur' => 'Nihai',
                'IsEmriTarihi' => '2026-05-14 00:12:07',
                'GorevNo' => 19,
            ],
            [
                'No' => 320,
                'SiparisNo' => 'STOK-20260515-1798',
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => 'sehpa zem alaves(kahve pinotex)',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 3922,
                'EslesenUrunTur' => 'Nihai',
                'IsEmriTarihi' => '2026-05-15 15:14:36',
                'GorevNo' => 162,
            ],
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 106,
                'UrunIDNo' => 3922,
                'SiparisSatirNo' => 214,
                'SiparisNo' => 'STOK-20260514-8852',
                'PersonelNo' => 27,
                'GorevBaslamaTarihi' => '18/05/2026 00:00',
                'Adet' => 0,
                'BekleyenAdet' => 5,
                'BolumAdiNo' => 1023,
                'AraUrunAdiNo' => 2945,
                'Onay' => 'hazir',
            ],
            [
                'No' => 383,
                'UrunIDNo' => 3922,
                'SiparisSatirNo' => 320,
                'SiparisNo' => 'STOK-20260515-1798',
                'PersonelNo' => 27,
                'GorevBaslamaTarihi' => '20/05/2026 00:00',
                'Adet' => 0,
                'BekleyenAdet' => 5,
                'BolumAdiNo' => 1023,
                'AraUrunAdiNo' => 2945,
                'Onay' => 'hazir',
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=IsEmriVerildi&ozelUretim=1');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 2]);

        $orders = collect($response->json('orders'));
        $currentStockOrder = $orders->firstWhere('no', 320);
        $previousStockOrder = $orders->firstWhere('no', 214);

        $this->assertNotNull($currentStockOrder);
        $this->assertNotNull($previousStockOrder);
        $this->assertSame(5, $currentStockOrder['personelAdet']);
        $this->assertSame(5, $currentStockOrder['uretimdeAdet']);
        $this->assertSame(5, $previousStockOrder['personelAdet']);
        $this->assertSame(5, $previousStockOrder['uretimdeAdet']);
    }

    public function test_get_order_pipeline_uses_bom_sequence_instead_of_department_number_order(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbBolum')->insert([
            ['No' => 5, 'BolumAdi' => 'Dosemehane'],
            ['No' => 6, 'BolumAdi' => 'Paketleme'],
            ['No' => 21, 'BolumAdi' => 'Boyahane'],
            ['No' => 22, 'BolumAdi' => 'YM Depo'],
            ['No' => 1023, 'BolumAdi' => 'Urun Depo'],
        ]);

        DB::table('tbPersonel')->insert([
            ['PersonelNo' => 16, 'Ad' => 'Sadi', 'Soyad' => 'Raslan', 'BolumAdiNo' => 5],
            ['PersonelNo' => 18, 'Ad' => 'Yusa', 'Soyad' => 'Garouhi', 'BolumAdiNo' => 22],
            ['PersonelNo' => 27, 'Ad' => 'Mustafa', 'Soyad' => 'Acar', 'BolumAdiNo' => 1023],
            ['PersonelNo' => 57, 'Ad' => 'Niyazi', 'Soyad' => '', 'BolumAdiNo' => 21],
            ['PersonelNo' => 1064, 'Ad' => 'Eren', 'Soyad' => '', 'BolumAdiNo' => 6],
        ]);

        DB::table('tbUrunler')->insert([
            'No' => 3925,
            'UrunID' => 'berjer final',
            'SistemAdi' => 'Berjer final',
            'AraAdlarYol' => '3084-3085-1:5253-5254-1:5254-5255-1:3085-5255-4:5255-5256-1',
        ]);

        DB::table('tbAraUrun')->insert([
            ['No' => 3084, 'AraUrunAdi' => 'YM ayak', 'BolumAdiNo' => 22, 'UrunCesidi' => 'Ham Madde', 'Yol' => ''],
            ['No' => 3085, 'AraUrunAdi' => 'BOY ayak', 'BolumAdiNo' => 21, 'UrunCesidi' => 'Ara Mamul', 'Yol' => '3084-1'],
            ['No' => 5253, 'AraUrunAdi' => 'Ara doseme girdisi', 'BolumAdiNo' => 5, 'UrunCesidi' => 'Ara Mamul', 'Yol' => ''],
            ['No' => 5254, 'AraUrunAdi' => 'DOS berjer', 'BolumAdiNo' => 5, 'UrunCesidi' => 'Ara Mamul', 'Yol' => '5253-1'],
            ['No' => 5255, 'AraUrunAdi' => 'PAK berjer', 'BolumAdiNo' => 6, 'UrunCesidi' => 'Ara Mamul', 'Yol' => '5254-1:3085-4'],
            ['No' => 5256, 'AraUrunAdi' => 'berjer final', 'BolumAdiNo' => 1023, 'UrunCesidi' => 'Nihayi Urun', 'Yol' => '5255-1'],
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 316,
            'SiparisNo' => 'STOK-20260515-4705',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'berjer final',
            'Adet' => 3,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 3925,
            'EslesenUrunTur' => 'Nihai',
            'IsEmriTarihi' => '2026-05-15 14:13:00',
            'GorevNo' => 156,
        ]);

        DB::table('tbPersonelGorev')->insert([
            ['No' => 355, 'UrunIDNo' => 3925, 'SiparisSatirNo' => 316, 'SiparisNo' => 'STOK-20260515-4705', 'PersonelNo' => 18, 'GorevBaslamaTarihi' => '18/05/2026 00:00', 'Adet' => 12, 'BekleyenAdet' => 0, 'BolumAdiNo' => 22, 'AraUrunAdiNo' => 3084, 'Onay' => ''],
            ['No' => 356, 'UrunIDNo' => 3925, 'SiparisSatirNo' => 316, 'SiparisNo' => 'STOK-20260515-4705', 'PersonelNo' => 57, 'GorevBaslamaTarihi' => '18/05/2026 00:00', 'Adet' => 0, 'BekleyenAdet' => 12, 'BolumAdiNo' => 21, 'AraUrunAdiNo' => 3085, 'Onay' => ''],
            ['No' => 357, 'UrunIDNo' => 3925, 'SiparisSatirNo' => 316, 'SiparisNo' => 'STOK-20260515-4705', 'PersonelNo' => 16, 'GorevBaslamaTarihi' => '19/05/2026 00:00', 'Adet' => 3, 'BekleyenAdet' => 0, 'BolumAdiNo' => 5, 'AraUrunAdiNo' => 5254, 'Onay' => ''],
            ['No' => 358, 'UrunIDNo' => 3925, 'SiparisSatirNo' => 316, 'SiparisNo' => 'STOK-20260515-4705', 'PersonelNo' => 1064, 'GorevBaslamaTarihi' => '20/05/2026 00:00', 'Adet' => 0, 'BekleyenAdet' => 3, 'BolumAdiNo' => 6, 'AraUrunAdiNo' => 5255, 'Onay' => ''],
            ['No' => 359, 'UrunIDNo' => 3925, 'SiparisSatirNo' => 316, 'SiparisNo' => 'STOK-20260515-4705', 'PersonelNo' => 27, 'GorevBaslamaTarihi' => '20/05/2026 00:00', 'Adet' => 0, 'BekleyenAdet' => 3, 'BolumAdiNo' => 1023, 'AraUrunAdiNo' => 5256, 'Onay' => ''],
        ]);

        DB::table('tbGorevler')->insert([
            'No' => 156,
            'UrunIDNo' => 3925,
            'SiparisSatirNo' => 316,
            'SiparisNo' => 'STOK-20260515-4705',
            'GorevBaslamaTarihi' => '15/05/2026 14:13',
            'GorevBitisTarihi' => '15/05/2026 14:13',
            'ToplamAdet' => 3,
            'BolumAdiNo' => 1023,
            'PersonelNo' => null,
            'Performans' => 0,
            'AraUrunAdiNo' => 5256,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrderPipeline&satirNo=316');

        $response->assertOk()->assertJson(['success' => true]);

        $visibleDepartments = collect($response->json('pipelineSteps'))
            ->filter(fn ($step) => ($step['status'] ?? '') !== 'baslanmadi')
            ->pluck('bolumAdi')
            ->values()
            ->all();

        $this->assertSame([
            'Dosemehane',
            'YM Depo',
            'Boyahane',
            'Paketleme',
            'Urun Depo',
        ], $visibleDepartments);

        $this->assertGreaterThan(
            array_search('Boyahane', $visibleDepartments, true),
            array_search('Paketleme', $visibleDepartments, true)
        );
    }

    public function test_get_order_pipeline_does_not_show_empty_department_as_next_stop(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbBolum')->insert([
            ['No' => 0, 'BolumAdi' => 'Yonetici'],
            ['No' => 1, 'BolumAdi' => 'Kesimhane'],
            ['No' => 4, 'BolumAdi' => 'Marangozhane'],
            ['No' => 5, 'BolumAdi' => 'Dosemehane'],
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 52,
            'Ad' => 'Riza',
            'Soyad' => '',
            'BolumAdiNo' => 4,
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 3127,
            'AraUrunAdi' => 'MAR/legna kanepe kasa kavak kesim',
            'BolumAdiNo' => 4,
            'UrunCesidi' => 'Ham Madde',
            'Yol' => '',
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 309,
            'SiparisNo' => 'STOK-20260515-3849',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'MAR/legna kanepe kasa kavak kesim',
            'Adet' => 30,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 3127,
            'EslesenUrunTur' => 'HamMadde',
            'IsEmriTarihi' => '2026-05-15 00:09:00',
            'GorevNo' => 114,
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 328,
            'UrunIDNo' => 50,
            'SiparisSatirNo' => 309,
            'SiparisNo' => 'STOK-20260515-3849',
            'PersonelNo' => 52,
            'GorevBaslamaTarihi' => '15/05/2026 00:00',
            'Adet' => 30,
            'BekleyenAdet' => 0,
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 3127,
            'Onay' => 'hazir',
        ]);

        DB::table('tbGorevler')->insert([
            'No' => 114,
            'UrunIDNo' => 50,
            'SiparisSatirNo' => 309,
            'SiparisNo' => 'STOK-20260515-3849',
            'GorevBaslamaTarihi' => '15/05/2026 00:09',
            'GorevBitisTarihi' => '15/05/2026 00:09',
            'ToplamAdet' => 30,
            'BolumAdiNo' => 4,
            'PersonelNo' => null,
            'Performans' => 0,
            'AraUrunAdiNo' => 3127,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrderPipeline&satirNo=309');

        $response->assertOk()->assertJson(['success' => true]);

        $departments = collect($response->json('pipelineSteps'))
            ->pluck('bolumAdi')
            ->values()
            ->all();

        $this->assertSame(['Marangozhane'], $departments);
        $this->assertNotContains('Yonetici', $departments);
    }

    public function test_excel_upload_does_not_passivate_independent_special_stock_orders(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyOrderMatchingTables();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-OLD',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Eski Siparis',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'GorevNo' => null,
            ],
            [
                'No' => 2,
                'SiparisNo' => 'STOK-WAITING',
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => 'Stok Uretimi',
                'Adet' => 4,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'GorevNo' => null,
            ],
            [
                'No' => 3,
                'SiparisNo' => 'STOK-WIP',
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => 'Stok Uretimi',
                'Adet' => 6,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'GorevNo' => 33,
            ],
            [
                'No' => 4,
                'SiparisNo' => 'S-WIP',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Normal Uretimde',
                'Adet' => 2,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'GorevNo' => 44,
            ],
        ]);

        $result = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'S-NEW',
                'urunAdi' => 'Yeni Siparis',
                'musteri' => 'Normal Musteri',
                'adet' => 1,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(1, $result['passivated']);
        $this->assertSame([4], array_column($result['pendingWorkOrders'], 'no'));

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'Pasif',
            'Aktif' => 0,
        ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 2,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
        ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 3,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
        ]);
    }

    public function test_excel_upload_keeps_set_children_active_and_rebuilds_stale_children(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyOrderMatchingTables();

        DB::table('tbUrunler')->insert([
            ['No' => 10, 'UrunID' => 'berjer-ikili', 'SistemAdi' => 'Ikili Berjer'],
            ['No' => 11, 'UrunID' => 'berjer-tekli', 'SistemAdi' => 'Tekli Berjer'],
            ['No' => 12, 'UrunID' => 'sehpa', 'SistemAdi' => 'Sehpa'],
        ]);

        $setNo = DB::table('tbSetTanimlari')->insertGetId([
            'ExcelSetAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 2 Adet Tekli Berjer 1 Adet Sehpa)',
            'SetAdi' => 'Ramos Set',
            'Aktif' => 1,
        ], 'No');

        DB::table('tbSetIcerikleri')->insert([
            ['SetNo' => $setNo, 'UrunNo' => 10, 'Adet' => 1],
            ['SetNo' => $setNo, 'UrunNo' => 11, 'Adet' => 2],
            ['SetNo' => $setNo, 'UrunNo' => 12, 'Adet' => 1],
        ]);

        $uploadedName = 'Ramos Bohem Çay Seti,Açık Krem(1 Adet İkili Berjer 2 Adet Tekli Berjer 1 Adet Sehpa)';

        $firstResult = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'SET-100',
                'urunAdi' => $uploadedName,
                'musteri' => 'Normal Musteri',
                'adet' => 1,
            ],
        ]);

        $this->assertTrue($firstResult['success']);
        $this->assertSame(1, $firstResult['matched']);

        $parentNo = (int) DB::table('tbSiparisSatir')
            ->where('SiparisNo', 'SET-100')
            ->where('SetMi', 1)
            ->value('No');

        $this->assertGreaterThan(0, $parentNo);
        $this->assertSame(3, DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $parentNo)->where('Aktif', 1)->count());
        $this->assertSame(4, DB::table('tbSiparisSatir')->where('Aktif', 1)->count());

        DB::table('tbSiparisSatir')
            ->where('AnaSetSatirNo', $parentNo)
            ->update(['Durum' => 'Pasif', 'Aktif' => 0]);

        app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'SET-100',
                'urunAdi' => $uploadedName,
                'musteri' => 'Normal Musteri',
                'adet' => 2,
            ],
        ]);

        $activeChildren = DB::table('tbSiparisSatir')
            ->where('AnaSetSatirNo', $parentNo)
            ->where('Aktif', 1)
            ->orderBy('UrunAdi')
            ->pluck('Adet', 'UrunAdi')
            ->toArray();

        $this->assertSame([
            'Ikili Berjer' => 2,
            'Sehpa' => 2,
            'Tekli Berjer' => 4,
        ], $activeChildren);
    }

    public function test_excel_upload_updates_existing_order_when_customer_note_changes(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyOrderMatchingTables();

        DB::table('tbUrunler')->insert([
            'No' => 3952,
            'UrunID' => 'legna-kanepe-gri',
            'SistemAdi' => 'Legna Bohem Kanepe, Gri',
            'SistemKodu' => '1KNPZEM00179',
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 279,
                'SiparisNo' => '11231622214',
                'Pazaryeri' => 'Trendyol',
                'Magaza' => 'ZEM HOME',
                'SiparisTarihi' => '2026-05-13 19:02:00',
                'Musteri' => 'Cebrail iftirak',
                'UrunAdi' => 'Legna Bohem Kanepe, Gri',
                'Adet' => 2,
                'MusteriNotu' => '',
                'KargoSonTeslim' => '2026-06-03 19:02:00',
                'Kategori' => 'KÖŞE VE KANEPE',
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 3952,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 231,
            ],
            [
                'No' => 501,
                'SiparisNo' => '11231622214',
                'Pazaryeri' => 'Trendyol',
                'Magaza' => 'ZEM HOME',
                'SiparisTarihi' => '2026-05-13 19:02:00',
                'Musteri' => 'Cebrail iftirak',
                'UrunAdi' => 'Legna Bohem Kanepe, Gri',
                'Adet' => 2,
                'MusteriNotu' => 'ACİL',
                'KargoSonTeslim' => '2026-06-03 19:02:00',
                'Kategori' => 'KÖŞE VE KANEPE',
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 3952,
                'EslesenUrunTur' => 'Nihai',
            ],
        ]);

        $result = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => '11231622214',
                'pazaryeri' => 'Trendyol',
                'magaza' => 'ZEM HOME',
                'siparisTarihi' => '2026-05-13 19:02:00',
                'musteri' => 'Cebrail iftirak',
                'urunAdi' => 'Legna Bohem Kanepe, Gri',
                'adet' => 2,
                'musteriNotu' => 'ACİL',
                'kargoSonTeslim' => '2026-06-03 19:02:00',
                'kategori' => 'KÖŞE VE KANEPE',
                'stokKodu' => '1KNPZEM00179',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['passivated']);
        $this->assertSame(2, DB::table('tbSiparisSatir')->where('SiparisNo', '11231622214')->count());
        $this->assertSame(1, DB::table('tbSiparisSatir')->where('SiparisNo', '11231622214')->where('Aktif', 1)->count());

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 279,
            'MusteriNotu' => 'ACİL',
            'Durum' => 'UretimdenKarsilaniyor',
            'Aktif' => 1,
            'BagliOlduguOzelUretimNo' => 231,
        ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 501,
            'Durum' => 'Pasif',
            'Aktif' => 0,
        ]);
    }

    public function test_excel_upload_matches_sets_with_marketplace_name_variations(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyOrderMatchingTables();

        DB::table('tbUrunler')->insert([
            ['No' => 10, 'UrunID' => 'kanepe-zem-ramos', 'SistemAdi' => 'kanepe zem ramos 2li zeugma v143 krem'],
            ['No' => 11, 'UrunID' => 'berjer-zem-ramos', 'SistemAdi' => 'berjer zem ramos zeugma v143 krem'],
        ]);

        $setNo = DB::table('tbSetTanimlari')->insertGetId([
            'ExcelSetAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 1 Adet Tekli Berjer)',
            'SetAdi' => 'Ramos Bohem Cay Seti Acik Krem',
            'Aktif' => 1,
        ], 'No');

        DB::table('tbSetIcerikleri')->insert([
            ['SetNo' => $setNo, 'UrunNo' => 10, 'Adet' => 1],
            ['SetNo' => $setNo, 'UrunNo' => 11, 'Adet' => 1],
        ]);

        $result = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'SET-RAMOS-100',
                'urunAdi' => 'Ramos Bohem Cay Seti, Acik Krem (1Adet Ikili Berjer + 1 Tane Tekli Berjer)',
                'musteri' => 'Normal Musteri',
                'adet' => 1,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['unmatched']);

        $parentNo = (int) DB::table('tbSiparisSatir')
            ->where('SiparisNo', 'SET-RAMOS-100')
            ->where('SetMi', 1)
            ->where('EslesmeYontemi', 'Set')
            ->value('No');

        $this->assertGreaterThan(0, $parentNo);
        $this->assertSame(2, DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $parentNo)->where('Aktif', 1)->count());

        DB::table('tbSiparisSatir')->insert([
            'No' => 900,
            'SiparisNo' => 'SET-RAMOS-101',
            'UrunAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 1 Adet Tekli Berjer)',
            'Adet' => 1,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 11,
            'EslesenUrunTur' => 'Nihai',
            'EslesmePuani' => 80,
            'EslesmeYontemi' => 'Otomatik',
            'SetMi' => 0,
        ]);

        $this->assertTrue(app(OrderSyncService::class)->tryApplySetDefinitionToOrder(900, 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 1 Adet Tekli Berjer)', 1));
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 900,
            'SetMi' => 1,
            'SetNo' => $setNo,
            'EslesmeYontemi' => 'Set',
        ]);
        $this->assertSame(2, DB::table('tbSiparisSatir')->where('AnaSetSatirNo', 900)->where('Aktif', 1)->count());

        $clearResponse = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=clearOrderMatch', [
                'siparisSatirNo' => 900,
            ]);

        $clearResponse->assertOk()->assertJson([
            'success' => true,
            'deletedChildren' => 2,
        ]);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 900,
            'SetMi' => 0,
            'SetNo' => null,
            'EslesenUrunNo' => null,
            'EslesmeYontemi' => null,
        ]);
        $this->assertSame(0, DB::table('tbSiparisSatir')->where('AnaSetSatirNo', 900)->count());

        DB::table('tbSiparisSatir')->insert([
            'No' => 901,
            'SiparisNo' => 'SET-RAMOS-102',
            'UrunAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet Berjer)',
            'Adet' => 1,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 11,
            'EslesenUrunTur' => 'Nihai',
            'EslesmePuani' => 80,
            'EslesmeYontemi' => 'Otomatik',
            'SetMi' => 0,
        ]);

        $rematchResponse = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=rematchOrders', []);

        $rematchResponse->assertOk()->assertJson([
            'success' => true,
            'clearedSetLikeMatches' => 1,
        ]);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 901,
            'SetMi' => 0,
            'EslesenUrunNo' => null,
            'EslesmeYontemi' => null,
        ]);
    }

    public function test_saving_existing_inactive_set_definition_reactivates_it(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyOrderMatchingTables();

        DB::table('tbUrunler')->insert([
            ['No' => 10, 'UrunID' => 'kanepe-zem-ramos', 'SistemAdi' => 'kanepe zem ramos 2li zeugma v143 krem'],
            ['No' => 11, 'UrunID' => 'berjer-zem-ramos', 'SistemAdi' => 'berjer zem ramos zeugma v143 krem'],
        ]);

        $setNo = DB::table('tbSetTanimlari')->insertGetId([
            'ExcelSetAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 1 Adet Tekli Berjer)',
            'SetAdi' => '',
            'Aktif' => 0,
        ], 'No');
        DB::table('tbSetIcerikleri')->insert([
            ['SetNo' => $setNo, 'UrunNo' => 10, 'Adet' => 1],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=addSetDefinition', [
                'excelSetAdi' => 'Ramos Bohem Çay Seti, Açık Krem (1 Adet İkili Berjer 1 Adet Tekli Berjer)',
                'setAdi' => '',
                'icerikler' => [
                    ['urunNo' => 10, 'adet' => 1],
                    ['urunNo' => 11, 'adet' => 1],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'setNo' => $setNo,
            ]);

        $this->assertDatabaseHas('tbSetTanimlari', [
            'No' => $setNo,
            'Aktif' => 1,
        ]);
        $this->assertSame(2, DB::table('tbSetIcerikleri')->where('SetNo', $setNo)->count());
    }

    public function test_deduct_stock_clears_special_production_binding_for_reserved_order(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();
        $this->createCriticalStockTables();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'puf-zem',
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 7,
            'Adet' => 5,
            'TamponMiktar' => 5,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'S-1001',
            'Musteri' => 'Test Musteri',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 3,
            'Durum' => 'UretimdenKarsilaniyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'BagliOlduguOzelUretimNo' => 99,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=deductStock', ['no' => 1]);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'StokKarsilandi',
            'Aktif' => 0,
            'BagliOlduguOzelUretimNo' => null,
        ]);

        $this->assertSame(2, intval(DB::table('tbBolumAraStok')->value('Adet')));

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'order_stock_out',
            'direction' => 'out',
            'component_no' => 11,
            'department_no' => 3,
            'quantity_before' => 5,
            'quantity_delta' => -3,
            'quantity_after' => 2,
            'buffer_before' => 5,
            'buffer_delta' => -3,
            'buffer_after' => 2,
            'order_item_no' => 1,
            'order_no' => 'S-1001',
        ]);
    }

    public function test_available_special_productions_ignores_stock_fulfilled_reservations(): void
    {
        $this->createLegacyOrdersTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 10,
                'SiparisNo' => 'OZEL-1',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
            ],
            [
                'No' => 11,
                'SiparisNo' => 'S-2001',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 4,
                'Durum' => 'StokKarsilandi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=getAvailableSpecialProductions', [
                'eslesenUrunNo' => 7,
                'eslesenUrunTur' => 'Nihai',
                'adet' => 4,
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.No', 10)
            ->assertJsonPath('data.0.bostaAdet', 5);
    }

    public function test_get_orders_displays_linked_waiting_order_as_gied_reserved(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-GIED-1',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-GIED-1',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 11,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 1)
            ->assertJsonPath('orders.0.durum', 'UretimdenKarsilaniyor')
            ->assertJsonPath('orders.0.rawDurum', 'UretimBekliyor')
            ->assertJsonPath('orders.0.bagliOlduguOzelUretimNo', 10)
            ->assertJsonPath('stats.uretimBekliyor', 0)
            ->assertJsonPath('stats.uretimdenKarsilaniyor', 1);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=UretimBekliyor')
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 0]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=UretimdenKarsilaniyor')
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 1);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=GiedYapilanlar')
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 1);
    }

    public function test_special_production_orders_expose_available_reservation_quantity(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 10,
            'SiparisNo' => 'STOK-GIED-AVAILABLE',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 11,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'GorevNo' => 101,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-GIED-AVAILABLE-1',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 3,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-GIED-AVAILABLE-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
            [
                'No' => 3,
                'SiparisNo' => 'S-GIED-PASSIVE',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'Pasif',
                'Aktif' => 0,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&ozelUretim=1');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 10)
            ->assertJsonPath('orders.0.rezerveEdilen', 4)
            ->assertJsonPath('orders.0.bostaKalan', 7)
            ->assertJsonPath('orders.0.musaitAdet', 7)
            ->assertJsonCount(2, 'orders.0.bagliSiparisler');
    }

    public function test_link_order_to_special_production_repairs_existing_gied_link_status(): void
    {
        $this->createLegacyOrdersTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-GIED-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-GIED-2',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 11,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=linkOrderToSpecialProduction', [
                'siparisNo' => 1,
                'ozelUretimNo' => 10,
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Sipariş zaten bu özel üretime bağlıydı; durum GİED rezervasyonu olarak düzeltildi.');

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'UretimdenKarsilaniyor',
            'BagliOlduguOzelUretimNo' => 10,
        ]);
    }

    public function test_bulk_gied_links_orders_to_available_special_production_capacity(): void
    {
        $this->createLegacyOrdersTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-BULK-GIED-1',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 2,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-BULK-GIED-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 3,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
            ],
            [
                'No' => 3,
                'SiparisNo' => 'S-BULK-GIED-3',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-BULK-GIED-1',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=linkOrdersToSpecialProductionBulk', [
                'satirNolar' => [1, 2, 3],
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'basarili' => 2])
            ->assertJsonCount(1, 'hatalar')
            ->assertJsonPath('baglantilar.0.ozelUretimNo', 10)
            ->assertJsonPath('baglantilar.1.ozelUretimNo', 10);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'UretimdenKarsilaniyor',
            'BagliOlduguOzelUretimNo' => 10,
        ]);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 2,
            'Durum' => 'UretimdenKarsilaniyor',
            'BagliOlduguOzelUretimNo' => 10,
        ]);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 3,
            'Durum' => 'UretimBekliyor',
            'BagliOlduguOzelUretimNo' => null,
        ]);
    }

    public function test_gied_can_split_one_order_across_multiple_special_productions(): void
    {
        $this->createLegacyOrdersTable();
        $this->createGiedAllocationTable();

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-SPLIT-GIED',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-SPLIT-GIED-1',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 10,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
            ],
            [
                'No' => 11,
                'SiparisNo' => 'STOK-SPLIT-GIED-2',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 8,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 102,
            ],
            [
                'No' => 20,
                'SiparisNo' => 'S-RESERVED-1',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 8,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 10,
            ],
            [
                'No' => 21,
                'SiparisNo' => 'S-RESERVED-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => 11,
            ],
        ]);

        $availableResponse = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=getAvailableSpecialProductions', [
                'eslesenUrunNo' => 7,
                'eslesenUrunTur' => 'Nihai',
                'adet' => 5,
            ]);

        $availableResponse
            ->assertOk()
            ->assertJson(['success' => true, 'splitRequired' => true])
            ->assertJsonPath('totalAvailable', 5)
            ->assertJsonPath('data.0.no', 10)
            ->assertJsonPath('data.0.bostaAdet', 2)
            ->assertJsonPath('data.0.ayrilacakAdet', 2)
            ->assertJsonPath('data.1.no', 11)
            ->assertJsonPath('data.1.bostaAdet', 3)
            ->assertJsonPath('data.1.ayrilacakAdet', 3);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=linkOrderToSpecialProduction', [
                'siparisNo' => 1,
                'ozelUretimNo' => 10,
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Sipariş özel üretimlere paylaştırılarak bağlandı!');

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'UretimdenKarsilaniyor',
            'BagliOlduguOzelUretimNo' => 10,
        ]);
        $this->assertDatabaseHas('tbSiparisOzelUretimRezervasyon', [
            'SiparisSatirNo' => 1,
            'OzelUretimSatirNo' => 10,
            'Adet' => 2,
            'Aktif' => 1,
        ]);
        $this->assertDatabaseHas('tbSiparisOzelUretimRezervasyon', [
            'SiparisSatirNo' => 1,
            'OzelUretimSatirNo' => 11,
            'Adet' => 3,
            'Aktif' => 1,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&ozelUretim=1')
            ->assertOk()
            ->assertJsonFragment([
                'no' => 10,
                'rezerveEdilen' => 10,
                'musaitAdet' => 0,
            ])
            ->assertJsonFragment([
                'no' => 11,
                'rezerveEdilen' => 8,
                'musaitAdet' => 0,
            ]);
    }

    public function test_get_orders_displays_stock_movement_backed_waiting_order_as_stock_fulfilled(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'S-STOCK-1',
            'Musteri' => 'Normal Musteri',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 1,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
        ]);

        DB::table('stock_movements')->insert([
            'movement_uuid' => 'stock-out-1',
            'movement_type' => 'order_stock_out',
            'direction' => 'out',
            'title_human' => 'Siparis icin stok cikisi',
            'quantity_before' => 5,
            'quantity_delta' => -1,
            'quantity_after' => 4,
            'buffer_before' => 5,
            'buffer_delta' => -1,
            'buffer_after' => 4,
            'order_item_no' => 1,
            'order_no' => 'S-STOCK-1',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 0])
            ->assertJsonPath('stats.uretimBekliyor', 0)
            ->assertJsonPath('stats.stokKarsilandi', 1);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=StokKarsilandi')
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 1)
            ->assertJsonPath('orders.0.durum', 'StokKarsilandi')
            ->assertJsonPath('orders.0.rawDurum', 'UretimBekliyor');
    }

    public function test_upload_orders_keeps_stock_fulfilled_rows_out_of_waiting_pool(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
            'SistemKodu' => 'PZ-1',
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-STOCK-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'StokKarsilandi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-WAIT-2',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Bekleyen Puf',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
            ],
        ]);

        app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'S-STOCK-2',
                'urunAdi' => 'Puf Zem',
                'musteri' => 'Normal Musteri',
                'adet' => 1,
                'stokKodu' => 'PZ-1',
            ],
        ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'StokKarsilandi',
            'Aktif' => 0,
        ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 2,
            'Durum' => 'Pasif',
            'Aktif' => 0,
        ]);
    }

    public function test_excel_upload_auto_stock_closes_gied_orders_missing_from_active_list(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
            'SistemKodu' => 'PZ-1',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'puf-zem',
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 7,
            'Adet' => 5,
            'TamponMiktar' => 5,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'S-GIED-MISSING',
            'Musteri' => 'Sevil Temel',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 1,
            'Durum' => 'UretimdenKarsilaniyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'BagliOlduguOzelUretimNo' => 10,
        ]);

        $result = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'S-STILL-ACTIVE',
                'urunAdi' => 'Puf Zem',
                'musteri' => 'Normal Musteri',
                'adet' => 1,
                'stokKodu' => 'PZ-1',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['giedAutoStockDeducted']);
        $this->assertSame([], $result['giedAutoStockErrors']);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'StokKarsilandi',
            'Aktif' => 0,
            'BagliOlduguOzelUretimNo' => null,
        ]);

        $this->assertSame(4, (int) DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet'));
        $this->assertSame(4, (int) DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('TamponMiktar'));

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'order_stock_out',
            'direction' => 'out',
            'component_no' => 11,
            'department_no' => 3,
            'quantity_before' => 5,
            'quantity_delta' => -1,
            'quantity_after' => 4,
            'buffer_before' => 5,
            'buffer_delta' => -1,
            'buffer_after' => 4,
            'order_item_no' => 1,
            'order_no' => 'S-GIED-MISSING',
        ]);
    }

    public function test_excel_upload_reports_missing_gied_orders_when_stock_is_not_available(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
            'SistemKodu' => 'PZ-1',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'puf-zem',
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 7,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'S-GIED-NOSTOCK',
            'Musteri' => 'Sevil Temel',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 2,
            'Durum' => 'UretimdenKarsilaniyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'BagliOlduguOzelUretimNo' => 10,
        ]);

        $result = app(OrderSyncService::class)->uploadOrders([
            [
                'siparisNo' => 'S-STILL-ACTIVE',
                'urunAdi' => 'Puf Zem',
                'musteri' => 'Normal Musteri',
                'adet' => 1,
                'stokKodu' => 'PZ-1',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['giedAutoStockDeducted']);
        $this->assertSame(1, count($result['giedAutoStockErrors']));
        $this->assertSame(1, $result['giedAutoStockErrors'][0]['no']);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'UretimdenKarsilaniyor',
            'Aktif' => 1,
            'BagliOlduguOzelUretimNo' => 10,
        ]);

        $this->assertSame(0, (int) DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet'));
    }

    public function test_cancel_work_order_removes_related_tasks_and_reverses_stock_effects(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert([
            'No' => 17,
            'UrunID' => 'koltuk-zem',
            'SistemAdi' => 'Koltuk Zem',
        ]);

        DB::table('tbBolumAraStok')->insert([
            'No' => 1,
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 17,
            'Adet' => 10,
            'TamponMiktar' => 3,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'S-3001',
            'Musteri' => 'Normal Musteri',
            'UrunAdi' => 'Koltuk Zem',
            'Adet' => 3,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 17,
            'EslesenUrunTur' => 'Nihai',
            'GorevNo' => 77,
            'IsEmriTarihi' => '2026-04-29 10:00:00',
            'TamponDusumleri' => json_encode([['araNo' => 8, 'adet' => 2]]),
        ]);

        DB::table('tbBolumHavuz')->insert([
            'No' => 31,
            'UrunIDNo' => 17,
            'SiparisSatirNo' => 1,
            'SiparisNo' => 'S-3001',
            'GorevBaslangicTarihi' => '29/04/2026',
            'GorevBaslangicSaati' => '10:00',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'ToplamAdet' => 1,
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 55,
            'UrunIDNo' => 17,
            'SiparisSatirNo' => 1,
            'SiparisNo' => 'S-3001',
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '29/04/2026 11:00',
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbGorevler')->insert([
            [
                'No' => 77,
                'UrunIDNo' => 17,
                'SiparisSatirNo' => 1,
                'SiparisNo' => 'S-3001',
                'GorevBaslamaTarihi' => '29/04/2026 10:00',
                'GorevBitisTarihi' => '29/04/2026 10:00',
                'ToplamAdet' => 3,
                'BolumAdiNo' => 3,
                'PersonelNo' => null,
                'AraUrunAdiNo' => 8,
            ],
            [
                'No' => 78,
                'UrunIDNo' => 17,
                'SiparisSatirNo' => 1,
                'SiparisNo' => 'S-3001',
                'GorevBaslamaTarihi' => '29/04/2026 11:00',
                'GorevBitisTarihi' => '29/04/2026 12:00',
                'ToplamAdet' => 2,
                'BolumAdiNo' => 3,
                'PersonelNo' => 22,
                'AraUrunAdiNo' => 8,
            ],
        ]);

        DB::table('stock_movements')->insert([
            'movement_uuid' => '11111111-1111-4111-8111-111111111111',
            'stock_row_no' => 1,
            'component_no' => 8,
            'department_no' => 3,
            'product_no' => 17,
            'movement_type' => 'production_stock_in',
            'direction' => 'in',
            'title_human' => 'Uretimden stok girisi',
            'quantity_before' => 8,
            'quantity_delta' => 2,
            'quantity_after' => 10,
            'buffer_before' => 3,
            'buffer_delta' => 0,
            'buffer_after' => 3,
            'source_type' => 'personnel_task',
            'source_id' => '55',
            'order_item_no' => 1,
            'order_no' => 'S-3001',
            'work_order_no' => 77,
            'personnel_task_no' => 55,
            'happened_at' => now(),
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=cancelWorkOrder', ['satirNo' => 1]);

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('stockReversal.logged_movements_reversed', 1)
            ->assertJsonPath('stockReversal.stock_quantity_removed', 2);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 1,
            'Durum' => 'UretimBekliyor',
            'GorevNo' => null,
            'TamponDusumleri' => null,
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['SiparisSatirNo' => 1]);
        $this->assertDatabaseMissing('tbPersonelGorev', ['SiparisSatirNo' => 1]);
        $this->assertDatabaseMissing('tbGorevler', ['SiparisSatirNo' => 1]);

        $this->assertSame(8, intval(DB::table('tbBolumAraStok')->where('No', 1)->value('Adet')));
        $this->assertSame(5, intval(DB::table('tbBolumAraStok')->where('No', 1)->value('TamponMiktar')));

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in_reversed',
            'order_item_no' => 1,
            'work_order_no' => 77,
            'personnel_task_no' => 55,
            'quantity_delta' => -2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'work_order_buffer_released',
            'order_item_no' => null,
            'buffer_delta' => 2,
        ]);
    }

    public function test_cancel_special_stock_work_order_passivates_on_first_attempt_and_removes_ready_personnel_task(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyWorkOrderHistoryTable();

        DB::table('tbUrunler')->insert([
            'No' => 17,
            'UrunID' => 'MAR/basit kasa',
            'SistemAdi' => 'MAR/basit kasa',
        ]);
        DB::table('tbAraUrun')->insert([
            'No' => 8,
            'AraUrunAdi' => 'MAR/basit kasa',
            'BolumAdiNo' => 3,
            'Yol' => '',
        ]);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Montaj']);

        DB::table('tbSiparisSatir')->insert([
            'No' => 10,
            'SiparisNo' => 'STOK-20260430-7054',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'MAR/basit kasa',
            'Adet' => 500,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 17,
            'EslesenUrunTur' => 'Nihai',
            'GorevNo' => 101,
            'IsEmriTarihi' => '2026-04-30 23:32:00',
        ]);

        DB::table('tbBolumHavuz')->insert([
            'No' => 31,
            'UrunIDNo' => 17,
            'SiparisSatirNo' => 10,
            'SiparisNo' => 'STOK-20260430-7054',
            'GorevBaslangicTarihi' => '30/04/2026',
            'GorevBaslangicSaati' => '23:32',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 500,
            'ToplamAdet' => 500,
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 55,
            'UrunIDNo' => 17,
            'SiparisSatirNo' => 10,
            'SiparisNo' => 'STOK-20260430-7054',
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '30/04/2026 23:35',
            'Adet' => 500,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbGorevler')->insert([
            'No' => 101,
            'UrunIDNo' => 17,
            'SiparisSatirNo' => 10,
            'SiparisNo' => 'STOK-20260430-7054',
            'GorevBaslamaTarihi' => '30/04/2026 23:32',
            'GorevBitisTarihi' => '30/04/2026 23:32',
            'ToplamAdet' => 500,
            'BolumAdiNo' => 3,
            'PersonelNo' => null,
            'AraUrunAdiNo' => 8,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=cancelBulkWorkOrders', ['satirNolar' => [10]]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'iptalEdilen' => 1,
            ]);

        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 10,
            'Durum' => 'Pasif',
            'Aktif' => 0,
            'GorevNo' => null,
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['SiparisSatirNo' => 10]);
        $this->assertDatabaseMissing('tbPersonelGorev', ['SiparisSatirNo' => 10]);
        $this->assertDatabaseMissing('tbGorevler', ['SiparisSatirNo' => 10]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');
    }

    public function test_production_detail_summarizes_open_production_for_matched_product(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'puf-zem',
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolum')->insert([
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
            ['No' => 4, 'BolumAdi' => 'Döşemehane'],
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Cuma',
            'Soyad' => 'Yildirim',
            'BolumAdiNo' => 4,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-1001',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'IsEmriTarihi' => null,
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-1',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
                'IsEmriTarihi' => '2026-04-20 10:00:00',
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 11,
                'SiparisNo' => 'S-2001',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 2,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'IsEmriTarihi' => null,
                'BagliOlduguOzelUretimNo' => 10,
            ],
        ]);

        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 7,
            'GorevBaslangicTarihi' => '20/04/2026',
            'GorevBaslangicSaati' => '10:00',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'Adet' => 6,
            'ToplamAdet' => 8,
            'AdimSirasi' => 0,
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 7,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '20/04/2026 11:00',
            'Adet' => 3,
            'BekleyenAdet' => 2,
            'Onay' => 'false',
            'AraUrunAdiNo' => 11,
            'BolumAdiNo' => 4,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getProductionDetail&satirNo=1');

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('summary.total', 8)
            ->assertJsonPath('summary.waiting', 6)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.specialTotal', 5)
            ->assertJsonPath('summary.reserved', 2)
            ->assertJsonPath('summary.available', 3)
            ->assertJsonPath('stages.0.bolumAdi', 'Kesimhane')
            ->assertJsonPath('stages.1.personnel.0', 'Cuma Yildirim')
            ->assertJsonPath('relatedProductions.0.available', 3);
    }

    public function test_get_orders_exposes_special_production_available_quantity_for_gied_decision(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'puf-zem',
            'SistemAdi' => 'Puf Zem',
        ]);
        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'puf-zem',
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-GIED-DECISION',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 10,
                'SiparisNo' => 'STOK-GIED-DECISION',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 11,
                'SiparisNo' => 'S-GIED-RESERVED',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 2,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'BagliOlduguOzelUretimNo' => 10,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getOrders&durum=UretimBekliyor');

        $response
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonPath('orders.0.no', 1)
            ->assertJsonPath('orders.0.uretimOzelToplamAdet', 5)
            ->assertJsonPath('orders.0.uretimRezerveAdet', 2)
            ->assertJsonPath('orders.0.uretimMusaitAdet', 3);
    }

    public function test_planning_increment_consumes_pool_and_increases_ready_quantity(): void
    {
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '18/04/2026 10:00',
            'Adet' => 2,
            'BekleyenAdet' => 2,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 17,
            'GorevBaslangicTarihi' => '18/04/2026',
            'GorevBaslangicSaati' => '10:00',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 5,
            'ToplamAdet' => 5,
            'AdimSirasi' => 0,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('traceContextFromRecord')->twice()->andReturn([]);
        $bomService->shouldReceive('scopeQueryToTrace')->once()->andReturnUsing(fn ($query) => $query);
        $bomService->shouldReceive('hasOpenDescendantWork')->once()->with('8', [])->andReturnFalse();
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('8');
        $this->app->instance(BomService::class, $bomService);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/increment/5');

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 5,
            'Adet' => 3,
            'BekleyenAdet' => 2,
        ]);

        $this->assertDatabaseHas('tbBolumHavuz', [
            'No' => 7,
            'Adet' => 4,
            'ToplamAdet' => 4,
        ]);
    }

    public function test_personnel_task_delete_returns_open_quantity_to_pool(): void
    {
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbPersonelGorev')->insert([
            'No' => 9,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '18/04/2026 11:00',
            'Adet' => 6,
            'BekleyenAdet' => 2,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('traceContextFromRecord')->once()->andReturn([]);
        $bomService->shouldReceive('minAraUrunUretimiDenetle')
            ->once()
            ->withArgs(function ($urunIDNo, $yol, $araUrunAdiNo, $adet, $aciklama, $stokDurum, $tamponDusumleri, $traceContext) {
                return intval($urunIDNo) === 17
                    && $yol === ''
                    && $araUrunAdiNo === '8'
                    && $adet === 8
                    && $stokDurum === 'StokHaric'
                    && $traceContext === [];
            })
            ->andReturn(0);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('8');
        $this->app->instance(BomService::class, $bomService);

        $response = $this->actingAs($this->makePersonnelUser())
            ->deleteJson('/api/panel/task/9');

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Görev silindi ve 8 adet havuza geri aktarıldı.');

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 9]);
    }

    public function test_pool_task_assignment_rejects_personnel_from_different_department(): void
    {
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 17,
            'GorevBaslangicTarihi' => '28/04/2026',
            'GorevBaslangicSaati' => '15:13',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'ToplamAdet' => 1,
            'AdimSirasi' => 0,
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 4,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 1,
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Bu görev yalnızca aynı bölümdeki personele atanabilir.',
            ]);

        $this->assertDatabaseHas('tbBolumHavuz', [
            'No' => 7,
            'Adet' => 1,
            'ToplamAdet' => 1,
        ]);
        $this->assertSame(0, DB::table('tbPersonelGorev')->count());
    }

    public function test_pool_task_assignment_defaults_missing_pool_product_to_zero(): void
    {
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Dosemehane']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'Doseme parcasi', 'BolumAdiNo' => 3, 'Yol' => '']);
        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => null,
            'GorevBaslangicTarihi' => '03/06/2026',
            'GorevBaslangicSaati' => '09:55',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 2,
            'ToplamAdet' => 2,
            'AdimSirasi' => 0,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 3,
        ]);

        $scheduledAt = now()->addDay();
        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 2,
                'gorev_tarihi' => $scheduledAt->format('Y-m-d'),
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 0,
            'Adet' => 2,
            'BekleyenAdet' => 0,
            'GorevBaslamaTarihi' => $scheduledAt->format('d/m/Y') . ' 00:00',
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['No' => 7]);
    }

    public function test_planning_pool_assignment_works_without_legacy_status_column(): void
    {
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Dosemehane']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'Doseme parcasi', 'BolumAdiNo' => 3, 'Yol' => '']);
        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => null,
            'GorevBaslangicTarihi' => '03/06/2026',
            'GorevBaslangicSaati' => '09:55',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 2,
            'ToplamAdet' => 2,
            'AdimSirasi' => 0,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 3,
        ]);

        $scheduledAt = now()->addDay();
        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/pool/assign', [
                'pool_id' => 7,
                'personnel_no' => 22,
                'target_date' => $scheduledAt->format('Y-m-d'),
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 0,
            'Adet' => 2,
            'BekleyenAdet' => 0,
            'GorevBaslamaTarihi' => $scheduledAt->format('d/m/Y') . ' 00:00',
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['No' => 7]);
    }

    public function test_pool_task_list_locks_parent_readiness_but_allows_waiting_assignment(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert([
            ['No' => 1, 'BolumAdi' => 'Paketleme'],
            ['No' => 3, 'BolumAdi' => 'Marangozhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 8, 'AraUrunAdi' => 'PAK/koltuk', 'BolumAdiNo' => 1, 'Yol' => '9-8-1'],
            ['No' => 9, 'AraUrunAdi' => 'MAR/kol', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumHavuz')->insert([
            [
                'No' => 7,
                'UrunIDNo' => 17,
                'GorevBaslangicTarihi' => '04/05/2026',
                'GorevBaslangicSaati' => '09:28',
                'BolumAdiNo' => 1,
                'AraUrunAdiNo' => 8,
                'Adet' => 1,
                'ToplamAdet' => 1,
                'AdimSirasi' => 0,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 8,
                'UrunIDNo' => 17,
                'GorevBaslangicTarihi' => '04/05/2026',
                'GorevBaslangicSaati' => '09:28',
                'BolumAdiNo' => 3,
                'AraUrunAdiNo' => 9,
                'Adet' => 1,
                'ToplamAdet' => 1,
                'AdimSirasi' => 0,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 1,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/database/pool-tasks');

        $response->assertOk();
        $tasks = collect($response->json('data'))->keyBy('id');

        $this->assertSame(0, $tasks->get(7)['adet']);
        $this->assertSame(0, $tasks->get(7)['atanabilir_adet']);
        $this->assertTrue($tasks->get(7)['alt_gorev_bekliyor']);
        $this->assertSame(1, $tasks->get(8)['adet']);
        $this->assertFalse($tasks->get(8)['alt_gorev_bekliyor']);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 1,
                'gorev_tarihi' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'Onay' => 'hazir',
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['No' => 7]);
    }

    public function test_pool_task_assignment_keeps_amount_above_ready_quantity_waiting(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Kesimhane']);
        DB::table('tbAraUrun')->insert([
            ['No' => 8, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'Yol' => '9-8-1'],
            ['No' => 9, 'AraUrunAdi' => 'Ham parça', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 9,
            'Adet' => 1,
            'TamponMiktar' => 1,
        ]);
        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 17,
            'GorevBaslangicTarihi' => '28/04/2026',
            'GorevBaslangicSaati' => '15:13',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'ToplamAdet' => 3,
            'AdimSirasi' => 0,
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 3,
        ]);

        $scheduledAt = now()->addDay();
        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 3,
                'gorev_tarihi' => $scheduledAt->format('Y-m-d'),
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'BekleyenAdet' => 2,
            'GorevBaslamaTarihi' => $scheduledAt->format('d/m/Y') . ' 00:00',
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['No' => 7]);
    }

    public function test_pool_task_assignment_allows_zero_ready_quantity_as_waiting_task(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Kesimhane']);
        DB::table('tbAraUrun')->insert([
            ['No' => 8, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'Yol' => '9-8-1'],
            ['No' => 9, 'AraUrunAdi' => 'Ham parça', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 9,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 17,
            'GorevBaslangicTarihi' => '30/04/2026',
            'GorevBaslangicSaati' => '00:20',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 0,
            'ToplamAdet' => 1,
            'AdimSirasi' => 0,
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 3,
        ]);

        $scheduledAt = now()->addDay();
        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 1,
                'gorev_tarihi' => $scheduledAt->format('Y-m-d'),
            ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'GorevBaslamaTarihi' => $scheduledAt->format('d/m/Y') . ' 00:00',
        ]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['No' => 7]);
    }

    public function test_pool_task_assignment_rejects_past_scheduled_date(): void
    {
        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 1,
                'gorev_tarihi' => now()->subDay()->format('Y-m-d'),
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Görev tarihi bugünden önce olamaz.',
            ]);
    }

    public function test_personnel_panel_hides_tasks_until_selected_assignment_date(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Kesimhane']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'Yol' => '']);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 1,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 8,
                'BolumAdiNo' => 3,
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->addDay()->format('d/m/Y') . ' 00:00',
                'Adet' => 1,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 8,
                'BolumAdiNo' => 3,
            ],
        ]);

        $response = $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks');

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.No', 5);
    }

    public function test_personnel_my_tasks_uses_final_product_image_when_component_image_is_empty(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        \Illuminate\Support\Facades\Schema::table('tbAraUrun', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('Resim', 500)->nullable();
        });

        DB::table('tbBolum')->insert(['No' => 6, 'BolumAdi' => 'Paketleme']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 1016,
                'AraUrunAdi' => 'PAK/berjer zem favela(DEMONTE) wolf 05 sütlü kahve',
                'BolumAdiNo' => 6,
                'UrunCesidi' => 'Ara Mamül',
                'Yol' => '',
                'Resim' => '',
            ],
            [
                'No' => 1017,
                'AraUrunAdi' => 'berjer zem favela(DEMONTE) wolf 05 sütlü kahve',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
                'Yol' => '1016-1',
                'Resim' => 'favela demonte sütlü kahve.jpg',
            ],
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => null,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 1016,
            'BolumAdiNo' => 6,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks')
            ->assertOk()
            ->assertJsonPath('tasks.0.Resim', 'favela demonte sütlü kahve.jpg');
    }

    public function test_admin_assigned_ready_task_requires_personnel_start_approval(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Kesimhane']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'Yol' => '']);
        DB::table('tbBolumHavuz')->insert([
            'No' => 7,
            'UrunIDNo' => 17,
            'GorevBaslangicTarihi' => now()->format('d/m/Y'),
            'GorevBaslangicSaati' => '08:00',
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'ToplamAdet' => 1,
            'AdimSirasi' => 0,
        ]);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Ali',
            'Soyad' => 'Usta',
            'BolumAdiNo' => 3,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/pool-tasks/7/assign', [
                'personel_no' => 22,
                'adet' => 1,
                'gorev_tarihi' => now()->format('Y-m-d'),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $taskNo = (int) DB::table('tbPersonelGorev')->value('No');
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => $taskNo,
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 8,
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', $taskNo)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1);

        $this->actingAs($this->makePersonnelUser())
            ->postJson("/api/panel/task/{$taskNo}/complete", ['adet' => 1])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Bu görev üretime hazır. Önce görevi onaylayıp üretime geçin.',
            ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson("/api/panel/task/{$taskNo}/start")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => $taskNo,
            'Onay' => 'false',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.No', $taskNo);
    }

    public function test_personnel_can_start_ready_task_partially_and_keep_remainder_ready(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Berjer']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Montaj'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'MON/berjer', 'BolumAdiNo' => 2, 'Yol' => '11-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/berjer kasa', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 4,
            'TamponMiktar' => 4,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
            'Adet' => 4,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/start', ['adet' => 2])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'accepted_quantity' => 2,
                'remaining_quantity' => 2,
            ]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 5,
            'Adet' => 2,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
        ]);
        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 10,
            'Adet' => 2,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);
        $this->assertSame(2, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet')));
        $this->assertSame(2, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('TamponMiktar')));

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 1])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => false,
                'toplamAdet' => 1,
            ]);

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 5,
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
        ]);
        $this->assertSame(1, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->value('Adet')));
        $this->assertDatabaseHas('tbGorevler', [
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'ToplamAdet' => 1,
            'AraUrunAdiNo' => 10,
        ]);
    }

    public function test_personnel_assigned_list_promotes_leaf_waiting_task_to_ready_quantity(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'MAR/kol favela(DEMONTE) berjer', 'BolumAdiNo' => 3, 'Yol' => '']);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
            'Adet' => 0,
            'BekleyenAdet' => 2,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.No', 5)
            ->assertJsonPath('tasks.0.Adet', 2)
            ->assertJsonPath('tasks.0.BekleyenAdet', 0)
            ->assertJsonPath('tasks.0.ToplamAdet', 2)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1)
            ->assertJsonPath('tasks.0.Durum', 'Üretime Hazır');
    }

    public function test_personnel_assigned_list_groups_same_ready_component_for_display_without_merging_records(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Legna Köşe Takımı']);
        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert([
            'No' => 5283,
            'AraUrunAdi' => 'MAR/legna köşe takımı sırt MDF zımpara tırnaklı somun',
            'BolumAdiNo' => 4,
            'UrunCesidi' => 'Ara Mamül',
            'Yol' => '',
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 5283,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => 101,
                'SiparisNo' => 'SIP-101',
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 5283,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => 102,
                'SiparisNo' => 'SIP-102',
            ],
        ]);

        $response = $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.GrupMu', true)
            ->assertJsonPath('tasks.0.GrupKayitSayisi', 2)
            ->assertJsonPath('tasks.0.Adet', 16)
            ->assertJsonPath('tasks.0.ToplamAdet', 16);

        $taskIds = $response->json('tasks.0.GrupGorevNoListesi');
        sort($taskIds);

        $this->assertSame([5, 6], $taskIds);
        $this->assertCount(2, $response->json('tasks.0.GrupDetaylari'));
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 5, 'Adet' => 8, 'Onay' => 'hazir']);
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 6, 'Adet' => 8, 'Onay' => 'hazir']);
    }

    public function test_personnel_can_start_grouped_ready_tasks_partially_across_real_records(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Legna Köşe Takımı']);
        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert([
            'No' => 5283,
            'AraUrunAdi' => 'MAR/legna köşe takımı sırt MDF zımpara tırnaklı somun',
            'BolumAdiNo' => 4,
            'UrunCesidi' => 'Ara Mamül',
            'Yol' => '',
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 5283,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => 101,
                'SiparisNo' => 'SIP-101',
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 5283,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => 102,
                'SiparisNo' => 'SIP-102',
            ],
        ]);

        $group = $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->json('tasks.0');

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task-group/start', [
                'group_key' => $group['GrupAnahtari'],
                'task_ids' => $group['GrupGorevNoListesi'],
                'adet' => 10,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'accepted_quantity' => 10,
                'remaining_quantity' => 6,
            ]);

        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 5, 'Adet' => 8, 'BekleyenAdet' => 0, 'Onay' => 'false']);
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 6, 'Adet' => 2, 'BekleyenAdet' => 0, 'Onay' => 'false']);
        $this->assertDatabaseHas('tbPersonelGorev', [
            'PersonelNo' => 22,
            'AraUrunAdiNo' => 5283,
            'SiparisSatirNo' => 102,
            'Adet' => 6,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);
    }

    public function test_personnel_assigned_list_shows_due_and_scheduled_waiting_tasks_with_null_approval(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Legna Kanepe']);
        DB::table('tbBolum')->insert(['No' => 5, 'BolumAdi' => 'Döşemehane']);
        DB::table('tbAraUrun')->insert([
            ['No' => 8, 'AraUrunAdi' => 'DÖŞ/kanepe zem legna zeugma keten gri', 'BolumAdiNo' => 5, 'Yol' => ''],
            ['No' => 9, 'AraUrunAdi' => 'DÖŞ/kanepe zem legna zeugma keten krem', 'BolumAdiNo' => 5, 'Yol' => ''],
            ['No' => 10, 'AraUrunAdi' => 'DÖŞ/aktif üretim', 'BolumAdiNo' => 5, 'Yol' => ''],
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 0,
                'BekleyenAdet' => 8,
                'Onay' => null,
                'AraUrunAdiNo' => 8,
                'BolumAdiNo' => 5,
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->addDay()->format('d/m/Y') . ' 00:00',
                'Adet' => 0,
                'BekleyenAdet' => 8,
                'Onay' => null,
                'AraUrunAdiNo' => 9,
                'BolumAdiNo' => 5,
            ],
            [
                'No' => 7,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y') . ' 00:00',
                'Adet' => 0,
                'BekleyenAdet' => 8,
                'Onay' => 'false',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 5,
            ],
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('tasks.0.No', 5)
            ->assertJsonPath('tasks.0.BekleyenAdet', 8)
            ->assertJsonPath('tasks.0.Baslatilabilir', 0)
            ->assertJsonPath('tasks.0.Durum', 'Bekliyor')
            ->assertJsonPath('tasks.1.No', 6)
            ->assertJsonPath('tasks.1.BekleyenAdet', 8)
            ->assertJsonPath('tasks.1.Baslatilabilir', 0)
            ->assertJsonPath('tasks.1.Durum', 'Planlandı')
            ->assertJsonPath('tasks.1.ButonMetni', 'Tarihi bekliyor');
    }

    public function test_personnel_task_readiness_refresh_moves_unproducible_amount_to_waiting(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Paketleme'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'PAK/koltuk', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/koltuk', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
        ]);

        app(BomService::class)->personelGorevTabloGuncelle('11');

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 5,
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'Onay' => 'false',
        ]);
    }

    public function test_personnel_can_continue_active_task_that_was_left_as_waiting_after_partial_completion(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Paketleme']);
        DB::table('tbAraUrun')->insert(['No' => 8, 'AraUrunAdi' => 'PAK/koltuk', 'BolumAdiNo' => 3, 'Yol' => '']);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/my-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.No', 5)
            ->assertJsonPath('tasks.0.UretilebilirAdet', 1);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 1])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => true,
                'toplamAdet' => 0,
            ]);

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 5]);
        $this->assertDatabaseHas('tbGorevler', [
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'ToplamAdet' => 1,
            'AraUrunAdiNo' => 8,
        ]);
    }

    public function test_personnel_full_production_completion_removes_active_task(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert([
            'No' => 8,
            'AraUrunAdi' => 'MAR/duralit contes bench',
            'BolumAdiNo' => 3,
            'Yol' => '',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 1])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => true,
                'toplamAdet' => 0,
            ]);

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 5]);
        $this->assertDatabaseHas('tbGorevler', [
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'ToplamAdet' => 1,
            'AraUrunAdiNo' => 8,
        ]);
        $this->assertSame(1, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 8)->value('Adet')));
        $this->assertSame(1, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 8)->value('TamponMiktar')));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'component_no' => 8,
            'quantity_delta' => 1,
            'buffer_delta' => 1,
            'personnel_task_no' => 5,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/task/5')
            ->assertNotFound();
    }

    public function test_telegram_task_completed_notification_resolves_legacy_names(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 40)->default('string');
            $table->timestamps();
        });
        Schema::create('telegram_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64);
            $table->unsignedBigInteger('task_no')->nullable();
            $table->unsignedBigInteger('order_no')->nullable();
            $table->unsignedBigInteger('order_item_no')->nullable();
            $table->text('message_body');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['event_type', 'task_no']);
        });

        DB::table('app_settings')->insert([
            ['key' => 'telegram_notifications_enabled', 'value' => '1', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'telegram_notify_task_completed', 'value' => '1', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'telegram_bot_token', 'value' => 'test-token', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'telegram_chat_id', 'value' => '12345', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk', 'SistemAdi' => null]);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbPersonel')->insert([
            'PersonelNo' => 22,
            'Ad' => 'Cuma',
            'Soyad' => 'Yildirim',
            'BolumAdiNo' => 3,
        ]);
        DB::table('tbAraUrun')->insert([
            'No' => 8,
            'AraUrunAdi' => 'MAR/duralit contes bench',
            'BolumAdiNo' => 3,
            'Yol' => '',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 8,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 3,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 1])
            ->assertOk()
            ->assertJson(['success' => true, 'completed' => true]);

        $messageBody = (string) DB::table('telegram_notification_logs')
            ->where('event_type', 'task_completed')
            ->where('task_no', 5)
            ->value('message_body');

        $this->assertStringContainsString('🏭 Ürün: Koltuk', $messageBody);
        $this->assertStringContainsString('📦 Ara Ürün: MAR/duralit contes bench', $messageBody);
        $this->assertStringContainsString('👤 Personel: Cuma Yildirim', $messageBody);
        $this->assertStringContainsString('🏢 Departman: Marangozhane', $messageBody);
        $this->assertStringNotContainsString('Belirtilmedi', $messageBody);
    }

    public function test_free_stock_production_completion_adds_to_free_stock(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 42,
            'SiparisNo' => 'STOK-42',
            'Pazaryeri' => 'Stok',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'Marin Puf',
            'Adet' => 3,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 10,
            'EslesenUrunTur' => 'Ara',
            'GorevNo' => 6,
        ]);
        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Marin Puf']);
        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 10,
                'AraUrunAdi' => 'Marin Puf',
                'BolumAdiNo' => 4,
                'Yol' => '',
            ],
            [
                'No' => 99,
                'AraUrunAdi' => 'Marin Puf Nihai',
                'BolumAdiNo' => 4,
                'Yol' => '10-1-1',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 10,
            'UrunIDNo' => 17,
            'Adet' => 5,
            'TamponMiktar' => 2,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 3,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 4,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'STOK-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/complete', ['adet' => 3])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => true,
        ]);

        $this->assertSame(8, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->value('Adet')));
        $this->assertSame(8, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->value('TamponMiktar')));
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 42,
            'Durum' => 'StokKarsilandi',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'component_no' => 10,
            'quantity_delta' => 3,
            'buffer_delta' => 3,
            'order_item_no' => 42,
            'personnel_task_no' => 6,
        ]);
    }

    public function test_hammadde_stock_production_completion_adds_to_free_stock(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 309,
            'SiparisNo' => 'STOK-20260515-3849',
            'Pazaryeri' => 'Stok',
            'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
            'UrunAdi' => 'MAR/legna kanepe kasa kavak kesim',
            'Adet' => 30,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 3127,
            'EslesenUrunTur' => 'HamMadde',
            'GorevNo' => 328,
        ]);
        DB::table('tbUrunler')->insert(['No' => 50, 'UrunID' => 'MAR/legna kanepe kasa kavak kesim']);
        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert([
            'No' => 3127,
            'AraUrunAdi' => 'MAR/legna kanepe kasa kavak kesim',
            'UrunCesidi' => 'Ham Madde',
            'BolumAdiNo' => 4,
            'Yol' => '',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 3127,
            'UrunIDNo' => 50,
            'Adet' => 25,
            'TamponMiktar' => 2,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 328,
            'UrunIDNo' => 50,
            'PersonelNo' => 52,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 30,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 3127,
            'BolumAdiNo' => 4,
            'SiparisSatirNo' => 309,
            'SiparisNo' => 'STOK-20260515-3849',
        ]);

        $this->actingAs($this->makePersonnelUser(52))
            ->postJson('/api/panel/task/328/complete', ['adet' => 30])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => true,
        ]);

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 328]);
        $this->assertSame(55, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 3127)->value('Adet')));
        $this->assertSame(55, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 3127)->value('TamponMiktar')));
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 309,
            'Durum' => 'StokKarsilandi',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'component_no' => 3127,
            'quantity_delta' => 30,
            'buffer_delta' => 30,
            'order_item_no' => 309,
            'personnel_task_no' => 328,
        ]);
    }

    public function test_order_tracked_production_stays_reserved_and_unlocks_only_its_parent_task(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Puf Zem']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'TER/puf zem', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/puf zem', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 1,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 11,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 1,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 7,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 1,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'SiparisSatirNo' => 43,
                'SiparisNo' => 'SIP-43',
            ],
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 1])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet')));
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('TamponMiktar')));
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 6,
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 7,
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'Onay' => 'hazir',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'component_no' => 11,
            'quantity_delta' => 1,
            'buffer_delta' => 0,
            'order_item_no' => 42,
        ]);
    }

    public function test_order_tracked_completion_with_new_stock_row_logs_movement_and_unlocks_parent_task(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Puf Zem']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'TER/puf zem', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/puf zem', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 5,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 5,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 11,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 45,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 5,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 5])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbBolumAraStok', [
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'Adet' => 5,
            'TamponMiktar' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'component_no' => 11,
            'quantity_before' => 0,
            'quantity_delta' => 5,
            'quantity_after' => 5,
            'buffer_before' => 0,
            'buffer_delta' => 0,
            'buffer_after' => 0,
            'order_item_no' => 42,
            'personnel_task_no' => 5,
        ]);
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 6,
            'Adet' => 5,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);
    }

    public function test_assigned_ready_task_refreshes_same_component_order_held_stock(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Marin Puf']);
        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbAraUrun')->insert([
            'No' => 10,
            'AraUrunAdi' => 'Marin Puf',
            'BolumAdiNo' => 4,
            'Yol' => '',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 10,
            'UrunIDNo' => 17,
            'Adet' => 1,
            'TamponMiktar' => 0,
        ]);
        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'stock_row_no' => 1,
            'component_no' => 10,
            'department_no' => 4,
            'product_no' => 17,
            'movement_type' => 'production_stock_in',
            'direction' => 'in',
            'title_human' => 'Uretimden stok girisi',
            'quantity_before' => 0,
            'quantity_delta' => 1,
            'quantity_after' => 1,
            'buffer_before' => 0,
            'buffer_delta' => 0,
            'buffer_after' => 0,
            'order_item_no' => 42,
            'order_no' => 'SIP-42',
            'source_screen' => 'Test',
            'source_action' => 'Test',
            'actor_type' => 'system',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 0,
            'BekleyenAdet' => 1,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 4,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', 6)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1)
            ->assertJsonPath('tasks.0.Durum', 'Üretime Hazır');

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 6,
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
        ]);
    }

    public function test_assigned_ready_task_shows_child_stock_shortage_before_start(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Marin Puf']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Ürün Depo'],
            ['No' => 3, 'BolumAdi' => 'Paketleme'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Ürün Depo', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'PAK/bench zem marin welsoft beyaz', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', 6)
            ->assertJsonPath('tasks.0.Baslatilabilir', 0)
            ->assertJsonPath('tasks.0.Durum', 'Alt parça bekliyor')
            ->assertJsonPath('tasks.0.ButonMetni', 'Alt parça stoğu yetersiz')
            ->assertJsonPath('tasks.0.BeklemeNedeni', 'PAK/bench zem marin welsoft beyaz için yeterli alt parça stoğu yok.');
    }

    public function test_assigned_waiting_task_uses_completed_child_work_when_stock_movement_is_missing(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 42,
            'SiparisNo' => 'STOK-42',
            'UrunAdi' => 'Nora puf',
            'Adet' => 20,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'TamponDusumleri' => json_encode([['araNo' => 26, 'adet' => 20]]),
        ]);
        DB::table('tbUrunler')->insert(['No' => 3980, 'UrunID' => 'Nora puf']);
        DB::table('tbBolum')->insert([
            ['No' => 3, 'BolumAdi' => 'Terzihane'],
            ['No' => 4, 'BolumAdi' => 'Marangozhane'],
            ['No' => 5, 'BolumAdi' => 'Döşemehane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 26, 'AraUrunAdi' => 'MAR/basit kasa', 'BolumAdiNo' => 4, 'Yol' => ''],
            ['No' => 320, 'AraUrunAdi' => 'TER/puf zem nora zeugma v143 krem', 'BolumAdiNo' => 3, 'Yol' => ''],
            ['No' => 321, 'AraUrunAdi' => 'DÖŞ/puf zem nora zeugma v143 krem altın elkamet', 'BolumAdiNo' => 5, 'Yol' => '26-1:320-1'],
        ]);
        DB::table('tbBolumAraStok')->insert([
            ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 26, 'UrunIDNo' => 3980, 'Adet' => 20, 'TamponMiktar' => 0],
            ['BolumAdiNo' => 3, 'AraUrunAdiNo' => 320, 'UrunIDNo' => 3980, 'Adet' => 20, 'TamponMiktar' => 0],
        ]);
        DB::table('tbGorevler')->insert([
            'No' => 96,
            'UrunIDNo' => 3980,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'STOK-42',
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'GorevBitisTarihi' => now()->format('d/m/Y H:i'),
            'ToplamAdet' => 20,
            'BolumAdiNo' => 3,
            'PersonelNo' => 45,
            'AraUrunAdiNo' => 320,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 3980,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 0,
            'BekleyenAdet' => 20,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 321,
            'BolumAdiNo' => 5,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'STOK-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', 6)
            ->assertJsonPath('tasks.0.Adet', 20)
            ->assertJsonPath('tasks.0.BekleyenAdet', 0)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1)
            ->assertJsonPath('tasks.0.Durum', 'Üretime Hazır');
    }

    public function test_completed_child_work_does_not_double_count_existing_stock_movements(): void
    {
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbGorevler')->insert([
            'No' => 96,
            'UrunIDNo' => 3980,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'STOK-42',
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'GorevBitisTarihi' => now()->format('d/m/Y H:i'),
            'ToplamAdet' => 20,
            'BolumAdiNo' => 3,
            'PersonelNo' => 45,
            'AraUrunAdiNo' => 320,
        ]);
        DB::table('stock_movements')->insert([
            [
                'movement_uuid' => '00000000-0000-0000-0000-000000000320',
                'component_no' => 320,
                'movement_type' => 'production_stock_in',
                'direction' => 'in',
                'title_human' => 'Üretimden stok girişi',
                'quantity_delta' => 20,
                'source_type' => 'personnel_task',
                'source_id' => '96',
                'order_item_no' => 42,
                'order_no' => 'STOK-42',
                'happened_at' => now(),
            ],
            [
                'movement_uuid' => '00000000-0000-0000-0000-000000000321',
                'component_no' => 320,
                'movement_type' => 'production_component_consumed_by_parent',
                'direction' => 'out',
                'title_human' => 'Alt parça üst görevde tüketildi',
                'quantity_delta' => -20,
                'source_type' => 'personnel_task_start',
                'source_id' => '6',
                'order_item_no' => 42,
                'order_no' => 'STOK-42',
                'personnel_task_no' => 6,
                'happened_at' => now(),
            ],
        ]);

        $heldQuantity = app(BomService::class)->orderHeldStockQuantity(320, [
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'STOK-42',
        ]);

        $this->assertSame(0, $heldQuantity);
    }

    public function test_dependency_info_lists_supplier_and_sends_deduplicated_notification(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyMessagesTable();
        $this->createWorkOrderCenterTables();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Meyra Kanepe']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
            ['No' => 3, 'BolumAdi' => 'Döşeme'],
        ]);
        DB::table('tbPersonel')->insert([
            ['PersonelNo' => 22, 'Ad' => 'Bekleyen', 'Soyad' => 'Personel', 'BolumAdiNo' => 2],
            ['PersonelNo' => 33, 'Ad' => 'Ureten', 'Soyad' => 'Personel', 'BolumAdiNo' => 3],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'TER/süngerli kanepe', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'DÖŞ/süngerli kanepe', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 2,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 7,
                'UrunIDNo' => 17,
                'PersonelNo' => 33,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 3,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 11,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/task/6/dependency-info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_shortage', true)
            ->assertJsonPath('shortages.0.component_no', 11)
            ->assertJsonPath('shortages.0.missing_quantity', 2)
            ->assertJsonPath('shortages.0.suppliers.0.task_no', 7)
            ->assertJsonPath('shortages.0.suppliers.0.personnel_no', 33)
            ->assertJsonPath('shortages.0.suppliers.0.expected_quantity', 2)
            ->assertJsonPath('shortages.0.suppliers.0.open_quantity', 3);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/notify-dependency', [
                'component_no' => 11,
                'supplier_task_no' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_sent', false);

        $this->assertSame(1, DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-6-7-11%')
            ->count());

        $notificationMessage = DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-6-7-11%')
            ->value('Mesaj');

        $this->assertStringStartsWith('Üretim Akışı Bildirimi', $notificationMessage);
        $this->assertStringContainsString('Bekleyen görev: TER/süngerli kanepe (#6)', $notificationMessage);
        $this->assertStringContainsString('Beklenen parça: DÖŞ/süngerli kanepe', $notificationMessage);
        $this->assertStringContainsString('Beklenen adet: 2', $notificationMessage);
        $this->assertStringContainsString('Takip: BG-6-7-11', $notificationMessage);
        $this->assertStringNotContainsString('Sizde açık görünen adet', $notificationMessage);

        $this->actingAs($this->makePersonnelUser(33))
            ->getJson('/api/panel/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($this->makePersonnelUser(33))
            ->postJson('/api/panel/messages/mark-read')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated', 1);

        $this->assertSame(1, intval(DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-6-7-11%')
            ->value('Okundu')));

        $this->actingAs($this->makePersonnelUser(33))
            ->getJson('/api/panel/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/notify-dependency', [
                'component_no' => 11,
                'supplier_task_no' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_sent', true);

        $this->assertSame(1, DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-6-7-11%')
            ->count());
    }

    public function test_dependency_info_lists_related_supplier_from_another_order_trace(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createWorkOrderCenterTables();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Legna Kose']);
        DB::table('tbBolum')->insert([
            ['No' => 4, 'BolumAdi' => 'Marangozhane'],
            ['No' => 5, 'BolumAdi' => 'Döşeme'],
        ]);
        DB::table('tbPersonel')->insert([
            ['PersonelNo' => 22, 'Ad' => 'Ali İhsan', 'Soyad' => 'Yılmaz', 'BolumAdiNo' => 5],
            ['PersonelNo' => 33, 'Ad' => 'Orhan', 'Soyad' => 'Usta', 'BolumAdiNo' => 4],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'DÖŞ/köşe takımı zem legna zeugma keten gri sol', 'BolumAdiNo' => 5, 'Yol' => '11-10-2'],
            ['No' => 11, 'AraUrunAdi' => 'MAR/legna kanepe kol', 'BolumAdiNo' => 4, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 4,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 6,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 1,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 5,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'STOK-42',
            ],
            [
                'No' => 7,
                'UrunIDNo' => 17,
                'PersonelNo' => 33,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 8,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 11,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => 99,
                'SiparisNo' => 'STOK-99',
            ],
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/task/6/dependency-info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_shortage', true)
            ->assertJsonPath('shortages.0.component_no', 11)
            ->assertJsonPath('shortages.0.suppliers', [])
            ->assertJsonPath('shortages.0.related_suppliers.0.task_no', 7)
            ->assertJsonPath('shortages.0.related_suppliers.0.personnel_no', 33)
            ->assertJsonPath('shortages.0.related_suppliers.0.personnel_name', 'Orhan Usta')
            ->assertJsonPath('shortages.0.related_suppliers.0.order_no', 'STOK-99')
            ->assertJsonPath('shortages.0.related_suppliers.0.can_notify', false);
    }

    public function test_admin_planning_dependency_info_can_notify_supplier_personnel(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyMessagesTable();
        $this->createWorkOrderCenterTables();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Meyra Kanepe']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Marangozhane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbPersonel')->insert([
            ['PersonelNo' => 22, 'Ad' => 'Ergül', 'Soyad' => 'Usta', 'BolumAdiNo' => 2],
            ['PersonelNo' => 33, 'Ad' => 'Kesim', 'Soyad' => 'Personeli', 'BolumAdiNo' => 3],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'MAR/alaves berjer kol kesim', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/alaves berjer kol', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 26,
                'UrunIDNo' => 17,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 0,
                'BekleyenAdet' => 4,
                'Onay' => 'false',
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
            [
                'No' => 25,
                'UrunIDNo' => 17,
                'PersonelNo' => 33,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'Adet' => 4,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 11,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 42,
                'SiparisNo' => 'SIP-42',
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/planning/task/26/dependency-info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_shortage', true)
            ->assertJsonPath('task.personnel_no', 22)
            ->assertJsonPath('shortages.0.component_no', 11)
            ->assertJsonPath('shortages.0.missing_quantity', 4)
            ->assertJsonPath('shortages.0.suppliers.0.task_no', 25)
            ->assertJsonPath('shortages.0.suppliers.0.personnel_no', 33)
            ->assertJsonPath('shortages.0.suppliers.0.can_notify', true);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/task/26/notify-dependency', [
                'component_no' => 11,
                'supplier_task_no' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_sent', false);

        $message = DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-26-25-11%')
            ->value('Mesaj');

        $this->assertStringStartsWith('Üretim Akışı Bildirimi', $message);
        $this->assertStringContainsString('Bekleyen görev: MAR/alaves berjer kol kesim (#26)', $message);
        $this->assertStringContainsString('Beklenen parça: KES/alaves berjer kol', $message);
        $this->assertStringContainsString('Beklenen adet: 4', $message);
        $this->assertSame(0, intval(DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-26-25-11%')
            ->value('Okundu')));

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/task/26/notify-dependency', [
                'component_no' => 11,
                'supplier_task_no' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_sent', true);

        $this->assertSame(1, DB::table('tbIletisim')
            ->where('PersonelNo', 33)
            ->where('Mesaj', 'like', '%BG-26-25-11%')
            ->count());
    }

    public function test_assigned_ready_task_can_use_completed_unbuffered_child_stock(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Marin Puf']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'TER/marin puf', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/marin puf', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 1,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', 6)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1)
            ->assertJsonPath('tasks.0.ButonMetni', 'Görevi Kabul Et');

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/start')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet')));
    }

    public function test_assigned_waiting_task_refreshes_from_untraced_completed_child_stock(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Akilli Sehpa']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Marangozhane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'MAR/akilli sehpa kasa', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/akilli sehpa parca', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 3,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 0,
            'BekleyenAdet' => 3,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJsonPath('tasks.0.No', 6)
            ->assertJsonPath('tasks.0.Adet', 3)
            ->assertJsonPath('tasks.0.BekleyenAdet', 0)
            ->assertJsonPath('tasks.0.Baslatilabilir', 1)
            ->assertJsonPath('tasks.0.ButonMetni', 'Görevi Kabul Et');

        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 6,
            'Adet' => 3,
            'BekleyenAdet' => 0,
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/task/6/dependency-info')
            ->assertOk()
            ->assertJsonPath('task.ready_quantity', 3)
            ->assertJsonPath('task.waiting_quantity', 0)
            ->assertJsonPath('has_shortage', false);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/start', ['adet' => 3])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet')));
    }

    public function test_passive_continuing_order_completion_nets_stock_and_closes_order_immediately(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createStockMovementsTable();

        DB::table('tbSiparisSatir')->insert([
            'No' => 42,
            'SiparisNo' => 'SIP-42',
            'Musteri' => 'Normal Musteri',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 3,
            'Durum' => 'PasifDevamEden',
            'Aktif' => 0,
            'EslesenUrunNo' => 17,
            'EslesenUrunTur' => 'Nihai',
            'GorevNo' => 5,
        ]);
        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Puf Zem']);
        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Terzihane']);
        DB::table('tbAraUrun')->insert([
            'No' => 10,
            'AraUrunAdi' => 'Puf Zem',
            'BolumAdiNo' => 3,
            'Yol' => '',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 10,
            'UrunIDNo' => 17,
            'Adet' => 7,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 3,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 3,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/5/complete', ['adet' => 3])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'completed' => true,
            ]);

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 5]);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 42,
            'Durum' => 'Pasif',
            'Aktif' => 0,
        ]);
        $this->assertSame(7, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->value('Adet')));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_stock_in',
            'order_item_no' => 42,
            'quantity_delta' => 3,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'order_auto_stock_out',
            'source_type' => 'pasif_devam_eden_completion',
            'order_item_no' => 42,
            'quantity_delta' => -3,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getPasifDevamEden')
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->assertSame(1, DB::table('stock_movements')->where('movement_type', 'order_auto_stock_out')->count());
        $this->assertSame(7, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->value('Adet')));
    }

    public function test_starting_order_tracked_parent_consumes_child_stock(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createStockMovementsTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Puf Zem']);
        DB::table('tbBolum')->insert([
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
            ['No' => 3, 'BolumAdi' => 'Kesimhane'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'TER/puf zem', 'BolumAdiNo' => 2, 'Yol' => '11-10-1'],
            ['No' => 11, 'AraUrunAdi' => 'KES/puf zem', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 17,
            'Adet' => 1,
            'TamponMiktar' => 0,
        ]);
        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'stock_row_no' => 1,
            'component_no' => 11,
            'department_no' => 3,
            'product_no' => 17,
            'movement_type' => 'production_stock_in',
            'direction' => 'in',
            'title_human' => 'Uretimden stok girisi',
            'quantity_before' => 0,
            'quantity_delta' => 1,
            'quantity_after' => 1,
            'buffer_before' => 0,
            'buffer_delta' => 0,
            'buffer_after' => 0,
            'order_item_no' => 42,
            'order_no' => 'SIP-42',
            'source_screen' => 'Test',
            'source_action' => 'Test',
            'actor_type' => 'system',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tbPersonelGorev')->insert([
            'No' => 6,
            'UrunIDNo' => 17,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'hazir',
            'AraUrunAdiNo' => 10,
            'BolumAdiNo' => 2,
            'SiparisSatirNo' => 42,
            'SiparisNo' => 'SIP-42',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->postJson('/api/panel/task/6/start')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('Adet')));
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 11)->value('TamponMiktar')));
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 6,
            'Onay' => 'false',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'production_component_consumed_by_parent',
            'component_no' => 11,
            'quantity_delta' => -1,
            'buffer_delta' => 0,
            'order_item_no' => 42,
            'personnel_task_no' => 6,
        ]);
    }

    public function test_personnel_ready_list_ignores_legacy_issued_work_orders_without_personnel_owner(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyIssuedTasksTable();

        DB::table('tbVerilenGorevler')->insert([
            'No' => 1,
            'UrunIDNo' => 8,
            'GorevTarihi' => now(),
            'ToplamAdet' => 500,
            'Aciklama' => 'İptal edilmiş veya sahipsiz legacy iş emri',
        ]);

        $this->actingAs($this->makePersonnelUser())
            ->getJson('/api/panel/assigned-to-me')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(0, 'tasks');
    }

    public function test_bom_service_keeps_legacy_insert_per_pool_call(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbAraUrun')->insert([
            'No' => 8,
            'AraUrunAdi' => 'Koltuk',
            'BolumAdiNo' => 3,
            'Yol' => '',
        ]);

        $service = app(BomService::class);
        $service->minAraUrunUretimiDenetle(17, '', '8', 1, 'İlk iş emri', 'StokHaric');
        $service->minAraUrunUretimiDenetle(17, '', '8', 1, 'İkinci iş emri', 'StokHaric');

        $this->assertSame(2, DB::table('tbBolumHavuz')->where('AraUrunAdiNo', 8)->count());
        $this->assertSame(2, intval(DB::table('tbBolumHavuz')->where('AraUrunAdiNo', 8)->sum('ToplamAdet')));
    }

    public function test_bom_recursive_keeps_leaf_material_when_parent_is_only_theoretically_producible(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbAraUrun')->insert([
            ['No' => 100, 'AraUrunAdi' => 'Koltuk', 'BolumAdiNo' => 1, 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => '101-1'],
            ['No' => 101, 'AraUrunAdi' => 'Terzi parçası', 'BolumAdiNo' => 2, 'UrunCesidi' => 'Ara Mamül', 'Yol' => '102-1'],
            ['No' => 102, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'UrunCesidi' => 'Ham Madde', 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 102,
            'Adet' => 5,
            'TamponMiktar' => 1,
        ]);

        $service = app(BomService::class);
        $yol = $service->tumYolHazirla('100');
        $tamponDusumleri = [];
        $result = $service->minAraUrunUretimiDenetle(17, $yol, '100', 3, '', 'StokHaric', $tamponDusumleri);
        $service->isEmriVerRecursive('100', $result, '', $yol, 17, 'StokDahil', $tamponDusumleri);

        $this->assertDatabaseHas('tbBolumHavuz', ['AraUrunAdiNo' => 100, 'ToplamAdet' => 3]);
        $this->assertDatabaseHas('tbBolumHavuz', ['AraUrunAdiNo' => 101, 'ToplamAdet' => 3]);
        $this->assertDatabaseHas('tbBolumHavuz', ['AraUrunAdiNo' => 102, 'ToplamAdet' => 2]);
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 102)->value('TamponMiktar')));
    }

    public function test_bom_recursive_skips_leaf_material_when_parent_buffer_covers_parent(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbUrunler')->insert(['No' => 17, 'UrunID' => 'Koltuk']);
        DB::table('tbAraUrun')->insert([
            ['No' => 100, 'AraUrunAdi' => 'Koltuk', 'BolumAdiNo' => 1, 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => '101-1'],
            ['No' => 101, 'AraUrunAdi' => 'Terzi parçası', 'BolumAdiNo' => 2, 'UrunCesidi' => 'Ara Mamül', 'Yol' => '102-1'],
            ['No' => 102, 'AraUrunAdi' => 'Kesim parçası', 'BolumAdiNo' => 3, 'UrunCesidi' => 'Ham Madde', 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 101,
            'Adet' => 3,
            'TamponMiktar' => 3,
        ]);

        $service = app(BomService::class);
        $yol = $service->tumYolHazirla('100');
        $tamponDusumleri = [];
        $result = $service->minAraUrunUretimiDenetle(17, $yol, '100', 3, '', 'StokHaric', $tamponDusumleri);
        $service->isEmriVerRecursive('100', $result, '', $yol, 17, 'StokDahil', $tamponDusumleri);

        $this->assertDatabaseHas('tbBolumHavuz', ['AraUrunAdiNo' => 100, 'ToplamAdet' => 3]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['AraUrunAdiNo' => 101]);
        $this->assertDatabaseMissing('tbBolumHavuz', ['AraUrunAdiNo' => 102]);
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 101)->value('TamponMiktar')));
    }

    public function test_bom_buffer_release_is_clamped_like_legacy(): void
    {
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbAraUrun')->insert([
            ['No' => 8, 'AraUrunAdi' => 'Üst parça', 'BolumAdiNo' => 3, 'Yol' => '9-8-2'],
            ['No' => 9, 'AraUrunAdi' => 'Alt parça', 'BolumAdiNo' => 3, 'Yol' => ''],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 9,
            'Adet' => 5,
            'TamponMiktar' => 4,
        ]);

        app(BomService::class)->tamponStokKontrol('8', 3);

        $this->assertSame(5, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 9)->value('TamponMiktar')));
    }

    public function test_planning_date_change_allows_ready_quantity_without_waiting_quantity(): void
    {
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbPersonelGorev')->insert([
            'No' => 1,
            'UrunIDNo' => 10,
            'PersonelNo' => 22,
            'GorevBaslamaTarihi' => '04/05/2026 22:51',
            'Adet' => 1,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 55,
            'BolumAdiNo' => 4,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->putJson('/api/planning/task/1/date', ['date' => '2026-05-05'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $updatedTask = DB::table('tbPersonelGorev')->where('No', 1)->first();

        $this->assertNotNull($updatedTask);
        $this->assertStringStartsWith('05/05/2026 ', $updatedTask->GorevBaslamaTarihi);
        $this->assertSame(1, intval($updatedTask->Adet));
        $this->assertSame(0, intval($updatedTask->BekleyenAdet));
    }

    public function test_planning_date_change_merge_recalculates_ready_and_waiting_quantities(): void
    {
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 1,
                'UrunIDNo' => 10,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => '04/05/2026 22:51',
                'Adet' => 1,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 55,
                'BolumAdiNo' => 4,
            ],
            [
                'No' => 2,
                'UrunIDNo' => 10,
                'PersonelNo' => 22,
                'GorevBaslamaTarihi' => '05/05/2026 08:00',
                'Adet' => 2,
                'BekleyenAdet' => 3,
                'Onay' => 'false',
                'AraUrunAdiNo' => 55,
                'BolumAdiNo' => 4,
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->putJson('/api/planning/task/1/date', ['date' => '2026-05-05'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 1]);
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 2,
            'Adet' => 6,
            'BekleyenAdet' => 0,
        ]);
    }

    public function test_production_summary_returns_order_row_numbers_for_work_order_creation(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbUrunler')->insert([
            'No' => 77,
            'UrunID' => 'puf-c',
            'SistemAdi' => 'Sistem Puf C',
            'AraAdlarYol' => null,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 41,
                'SiparisNo' => 'S-41',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf C',
                'Adet' => 2,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'SetMi' => 0,
            ],
            [
                'No' => 42,
                'SiparisNo' => 'S-42',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf C',
                'Adet' => 3,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'SetMi' => 0,
            ],
            [
                'No' => 43,
                'SiparisNo' => 'STOK-43',
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => 'Puf C',
                'Adet' => 9,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Özel Üretim',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'SetMi' => 0,
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getSummary')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('kategoriList.0', 'Puf')
            ->assertJsonPath('summary.0.SatirNolar', [41, 42])
            ->assertJsonPath('summary.0.ToplamAdet', 5);
    }

    public function test_production_summary_does_not_double_count_duplicate_match_cache_rows(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        Schema::create('tbUrunEslestirmeOnbellek', function (Blueprint $table) {
            $table->increments('No');
            $table->string('ExcelUrunAdi', 300);
            $table->integer('EslesenUrunNo');
            $table->string('EslesenUrunTur', 10);
            $table->dateTime('OlusturmaTarihi')->nullable();
        });

        DB::table('tbUrunler')->insert([
            'No' => 77,
            'UrunID' => 'puf-c',
            'SistemAdi' => 'Sistem Puf C',
            'AraAdlarYol' => null,
        ]);

        DB::table('tbUrunEslestirmeOnbellek')->insert([
            [
                'No' => 1,
                'ExcelUrunAdi' => 'Puf C',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'OlusturmaTarihi' => '2026-05-14 10:00:00',
            ],
            [
                'No' => 2,
                'ExcelUrunAdi' => 'Puf C',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'OlusturmaTarihi' => '2026-05-14 10:05:00',
            ],
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 41,
                'SiparisNo' => 'S-41',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf C',
                'Adet' => 2,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'SetMi' => 0,
            ],
            [
                'No' => 42,
                'SiparisNo' => 'S-42',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf C',
                'Adet' => 3,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'SetMi' => 0,
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getSummary')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('summary.0.SatirNolar', [41, 42])
            ->assertJsonPath('summary.0.SiparisSayisi', 2)
            ->assertJsonPath('summary.0.ToplamAdet', 5);
    }

    public function test_get_summary_exposes_special_production_available_quantity_for_gied_decision(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbUrunler')->insert([
            'No' => 77,
            'UrunID' => 'puf-c',
            'SistemAdi' => 'Sistem Puf C',
            'AraAdlarYol' => null,
        ]);

        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 41,
                'SiparisNo' => 'S-41',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf C',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'BagliOlduguOzelUretimNo' => null,
                'SetMi' => 0,
            ],
            [
                'No' => 50,
                'SiparisNo' => 'STOK-50',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf C Stok Üretimi',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'Kategori' => 'Özel Üretim',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
                'BagliOlduguOzelUretimNo' => null,
                'SetMi' => 0,
            ],
            [
                'No' => 51,
                'SiparisNo' => 'S-51',
                'Musteri' => 'Rezerve Musteri',
                'UrunAdi' => 'Puf C Rezerve',
                'Adet' => 2,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'Kategori' => 'Puf',
                'EslesenUrunNo' => 77,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'BagliOlduguOzelUretimNo' => 50,
                'SetMi' => 0,
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/SiparisApi.ashx?action=getSummary')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('summary.0.ToplamAdet', 1)
            ->assertJsonPath('summary.0.UretimToplamAdet', 5)
            ->assertJsonPath('summary.0.UretimRezerveAdet', 2)
            ->assertJsonPath('summary.0.UretimMusaitAdet', 3);
    }

    public function test_stock_included_manual_component_work_order_uses_root_free_stock_before_opening_tasks(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyIssuedTasksTable();

        DB::table('tbBolum')->insert([
            ['No' => 1, 'BolumAdi' => 'Kesimhane'],
            ['No' => 2, 'BolumAdi' => 'Terzihane'],
        ]);

        DB::table('tbAraUrun')->insert([
            [
                'No' => 910,
                'AraUrunAdi' => 'TER/kanepe zem legna zeugma keten gri',
                'Performans' => 0,
                'BolumAdiNo' => 2,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara Mamül',
                'Yol' => '911-1',
            ],
            [
                'No' => 911,
                'AraUrunAdi' => 'KES/kanepe zem legna zeugma keten gri',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
        ]);

        DB::table('tbBolumAraStok')->insert([
            [
                'BolumAdiNo' => 2,
                'AraUrunAdiNo' => 910,
                'UrunIDNo' => 0,
                'Adet' => 31,
                'TamponMiktar' => 16,
            ],
        ]);

        $result = app(\App\Services\WorkOrderService::class)->createLegacyManualWorkOrder(
            910,
            'Ara Mamül',
            20,
            'StokDahil',
            '',
            ['siparisSatirNo' => 4414, 'siparisNo' => 'STOK-20260520-4414']
        );

        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['netProductionQuantity']);
        $this->assertSame([['araNo' => 910, 'adet' => 16]], $result['tamponDusumleri']);

        $rootPool = DB::table('tbBolumHavuz')->where('AraUrunAdiNo', 910)->first();
        $childPool = DB::table('tbBolumHavuz')->where('AraUrunAdiNo', 911)->first();
        $workOrder = DB::table('tbGorevler')->where('AraUrunAdiNo', 910)->first();
        $issuedTask = DB::table('tbVerilenGorevler')->where('UrunIDNo', 910)->first();

        $this->assertSame(4, intval($rootPool->ToplamAdet ?? 0));
        $this->assertSame(4, intval($childPool->ToplamAdet ?? 0));
        $this->assertSame(4, intval($workOrder->ToplamAdet ?? 0));
        $this->assertSame(4, intval($issuedTask->ToplamAdet ?? 0));
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 910)->value('TamponMiktar')));
    }

    private function makeAdminUser(): User
    {
        $user = new User();
        $user->forceFill([
            'id' => 1,
            'name' => 'Admin',
            'surname' => 'User',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'department_id' => null,
            'personnel_no' => 1,
        ]);

        return $user;
    }

    private function makePersonnelUser(int $personnelNo = 22): User
    {
        $user = new User();
        $user->forceFill([
            'id' => $personnelNo,
            'name' => 'Personel',
            'surname' => 'User',
            'email' => 'personel' . $personnelNo . '@example.com',
            'password' => 'secret',
            'department_id' => 3,
            'personnel_no' => $personnelNo,
        ]);

        return $user;
    }
}
