<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BomService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class WorkOrderCenterTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_legacy_history_redirects_to_center_and_center_endpoints_return_story_data(): void
    {
        $this->createWorkOrderCenterTables();

        DB::table('work_order_events')->insert([
            [
                'id' => 1,
                'event_uuid' => '00000000-0000-0000-0000-000000000001',
                'correlation_id' => '10000000-0000-0000-0000-000000000001',
                'aggregate_type' => 'order_item',
                'aggregate_id' => 101,
                'order_item_no' => 101,
                'order_no' => 'S-4001',
                'work_order_no' => 501,
                'event_type' => 'work_order_created_single',
                'event_group' => 'create',
                'source_screen' => 'Siparis Yonetimi',
                'source_action' => 'Is Emri Ver',
                'actor_type' => 'admin',
                'actor_id' => '1',
                'actor_name' => 'Admin User',
                'status_before' => 'UretimBekliyor',
                'status_after' => 'IsEmriVerildi',
                'title_human' => 'Siparise is emri verildi',
                'summary_human' => 'Admin User, Siparis Yonetimi ekranindan siparise is emri verdi.',
                'next_step_human' => 'Havuz veya personel gorevi takibi yapilmali.',
                'payload_before' => json_encode(['Durum' => 'UretimBekliyor']),
                'payload_after' => json_encode(['Durum' => 'IsEmriVerildi']),
                'context' => json_encode(['source' => 'test']),
                'happened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'event_uuid' => '00000000-0000-0000-0000-000000000002',
                'correlation_id' => '10000000-0000-0000-0000-000000000002',
                'aggregate_type' => 'personnel_task',
                'aggregate_id' => 9001,
                'order_item_no' => null,
                'order_no' => null,
                'work_order_no' => null,
                'event_type' => 'planning_incremented',
                'event_group' => 'planning',
                'source_screen' => 'Uretim Planlama',
                'source_action' => 'Planlama +1',
                'actor_type' => 'admin',
                'actor_id' => '1',
                'actor_name' => 'Admin User',
                'status_before' => 'Planlandi',
                'status_after' => 'Planlandi',
                'title_human' => 'Planlamada gorev adedi artirildi',
                'summary_human' => 'Planlama ekranindan gorev adedi artirildi.',
                'next_step_human' => 'Artirilan miktar icin personel uretim girisi yapmali.',
                'payload_before' => json_encode(['No' => 9001, 'Adet' => 2]),
                'payload_after' => json_encode(['No' => 9001, 'Adet' => 3]),
                'context' => json_encode(['source' => 'test']),
                'happened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('work_order_snapshots')->insert([
            'aggregate_type' => 'order_item',
            'aggregate_id' => 101,
            'order_item_no' => 101,
            'order_no' => 'S-4001',
            'work_order_no' => 501,
            'current_status' => 'IsEmriVerildi',
            'current_stage' => 'issued',
            'current_holder_type' => 'department',
            'current_holder_id' => '3',
            'current_holder_name' => 'Dikim',
            'linked_special_production_no' => null,
            'next_expected_action' => 'Havuz veya personel gorevi takibi yapilmali.',
            'last_event_id' => 1,
            'last_changed_at' => now(),
            'alert_count' => 1,
            'snapshot' => json_encode([
                'alerts' => [
                    [
                        'code' => 'missing_pool',
                        'severity' => 'medium',
                        'message' => 'Henuz havuz kaydi olusmamis.',
                        'suggested_fix' => 'Is emri senkronunu kontrol edin.',
                    ],
                ],
                'counts' => [
                    'pool' => 0,
                    'active_tasks' => 0,
                    'completed_tasks' => 0,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->makeAdminUser())
            ->get('/is-emri-gecmisi')
            ->assertRedirect(route('workorders.center'));

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/feed')
            ->assertOk()
            ->assertJsonPath('data.0.title_human', 'Planlamada gorev adedi artirildi')
            ->assertJsonPath('data.1.snapshot.current_status', 'IsEmriVerildi');

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/order_item/101')
            ->assertOk()
            ->assertJsonPath('data.entity.current_status', 'IsEmriVerildi')
            ->assertJsonPath('data.entity.current_holder_name', 'Dikim')
            ->assertJsonCount(1, 'data.alerts')
            ->assertJsonPath('data.insights.event_count', 1);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/personnel_task/9001')
            ->assertOk()
            ->assertJsonPath('data.entity.type', 'personnel_task')
            ->assertJsonPath('data.entity.current_status', 'Planlandi')
            ->assertJsonPath('data.timeline.0.event_type', 'planning_incremented');

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/lookups')
            ->assertOk()
            ->assertJsonFragment(['Is Emri Ver'])
            ->assertJsonFragment(['Admin User']);
    }

    public function test_deduct_stock_creates_center_event_and_snapshot(): void
    {
        $this->createWorkOrderCenterTables();
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createCriticalStockTables();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();

        DB::table('tbBolum')->insert([
            'No' => 3,
            'BolumAdi' => 'Dikim',
        ]);

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
            'GorevNo' => 701,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=deductStock', ['no' => 1])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('work_order_events', [
            'event_type' => 'stock_deducted',
            'order_item_no' => 1,
            'work_order_no' => 701,
        ]);

        $this->assertDatabaseHas('work_order_snapshots', [
            'aggregate_type' => 'order_item',
            'aggregate_id' => 1,
            'current_status' => 'StokKarsilandi',
        ]);
    }

    public function test_planning_increment_creates_generic_center_event(): void
    {
        $this->createWorkOrderCenterTables();
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
        $bomService->shouldReceive('traceContextFromRecord')->once()->andReturn([]);
        $bomService->shouldReceive('scopeQueryToTrace')->once()->andReturnUsing(fn ($query) => $query);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('8');
        $this->app->instance(BomService::class, $bomService);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/increment/5')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('work_order_events', [
            'event_type' => 'planning_incremented',
            'aggregate_type' => 'personnel_task',
            'aggregate_id' => 5,
            'personnel_task_no' => 5,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/personnel_task/5')
            ->assertOk()
            ->assertJsonPath('data.entity.current_status', null)
            ->assertJsonPath('data.timeline.0.event_type', 'planning_incremented');
    }

    public function test_feed_filters_and_csv_export_work_with_actor_and_alerts(): void
    {
        $this->createWorkOrderCenterTables();

        DB::table('work_order_events')->insert([
            [
                'id' => 11,
                'event_uuid' => '00000000-0000-0000-0000-000000000011',
                'correlation_id' => '10000000-0000-0000-0000-000000000011',
                'aggregate_type' => 'order_item',
                'aggregate_id' => 201,
                'order_item_no' => 201,
                'order_no' => 'S-5001',
                'work_order_no' => 601,
                'event_type' => 'stock_deducted',
                'event_group' => 'stock',
                'source_screen' => 'Siparis Yonetimi',
                'source_action' => 'Stoktan Dus',
                'actor_type' => 'admin',
                'actor_id' => '1',
                'actor_name' => 'Cuma Yildirim',
                'status_before' => 'UretimBekliyor',
                'status_after' => 'StokKarsilandi',
                'title_human' => 'Siparis stoktan karsilandi',
                'summary_human' => 'Cuma Yildirim siparisi stoktan karsiladi.',
                'next_step_human' => 'Kayit stoktan kapanmis durumda.',
                'payload_before' => json_encode(['Durum' => 'UretimBekliyor', 'Adet' => 2]),
                'payload_after' => json_encode(['Durum' => 'StokKarsilandi', 'Adet' => 2]),
                'context' => json_encode(['source' => 'test']),
                'happened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'event_uuid' => '00000000-0000-0000-0000-000000000012',
                'correlation_id' => '10000000-0000-0000-0000-000000000012',
                'aggregate_type' => 'order_item',
                'aggregate_id' => 202,
                'order_item_no' => 202,
                'order_no' => 'S-5002',
                'work_order_no' => 602,
                'event_type' => 'work_order_created_single',
                'event_group' => 'create',
                'source_screen' => 'Siparis Yonetimi',
                'source_action' => 'Is Emri Ver',
                'actor_type' => 'admin',
                'actor_id' => '2',
                'actor_name' => 'Baska Kullanici',
                'status_before' => 'UretimBekliyor',
                'status_after' => 'IsEmriVerildi',
                'title_human' => 'Siparise is emri verildi',
                'summary_human' => 'Baska Kullanici siparise is emri verdi.',
                'next_step_human' => 'Havuz bekleniyor.',
                'payload_before' => json_encode(['Durum' => 'UretimBekliyor']),
                'payload_after' => json_encode(['Durum' => 'IsEmriVerildi']),
                'context' => json_encode(['source' => 'test']),
                'happened_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('work_order_snapshots')->insert([
            [
                'aggregate_type' => 'order_item',
                'aggregate_id' => 201,
                'order_item_no' => 201,
                'order_no' => 'S-5001',
                'work_order_no' => 601,
                'current_status' => 'StokKarsilandi',
                'current_stage' => 'fulfilled_from_stock',
                'current_holder_name' => 'Stok',
                'next_expected_action' => 'Sevk kontrolu yapilmali.',
                'alert_count' => 2,
                'snapshot' => json_encode(['alerts' => [['message' => 'Ornek uyari']]]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aggregate_type' => 'order_item',
                'aggregate_id' => 202,
                'order_item_no' => 202,
                'order_no' => 'S-5002',
                'work_order_no' => 602,
                'current_status' => 'IsEmriVerildi',
                'current_stage' => 'issued',
                'current_holder_name' => 'Dikim',
                'next_expected_action' => 'Havuz bekleniyor.',
                'alert_count' => 0,
                'snapshot' => json_encode(['alerts' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/feed?actor_name=' . urlencode('Cuma Yildirim') . '&has_alerts=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_no', 'S-5001')
            ->assertJsonPath('data.0.snapshot.alert_count', 2);

        $response = $this->actingAs($this->makeAdminUser())
            ->get('/api/work-order-center/export?source_action=' . urlencode('Stoktan Dus') . '&has_alerts=1');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Siparis stoktan karsilandi', $content);
        $this->assertStringContainsString('Cuma Yildirim', $content);
        $this->assertStringNotContainsString('Baska Kullanici', $content);
    }

    public function test_feed_supports_pagination_and_entity_builds_diagnostics(): void
    {
        $this->createWorkOrderCenterTables();

        DB::table('work_order_events')->insert([
            [
                'id' => 21,
                'event_uuid' => '00000000-0000-0000-0000-000000000021',
                'correlation_id' => '10000000-0000-0000-0000-000000000021',
                'aggregate_type' => 'order_item',
                'aggregate_id' => 301,
                'order_item_no' => 301,
                'order_no' => 'S-7001',
                'work_order_no' => 801,
                'event_type' => 'state_seeded',
                'event_group' => 'system',
                'source_screen' => null,
                'source_action' => null,
                'actor_type' => 'system',
                'actor_id' => null,
                'actor_name' => null,
                'status_before' => null,
                'status_after' => 'IsEmriVerildi',
                'title_human' => 'Mevcut siparis durumu merkeze aktarildi',
                'summary_human' => 'Tohum kayit.',
                'next_step_human' => null,
                'payload_before' => json_encode([]),
                'payload_after' => json_encode(['Durum' => 'IsEmriVerildi']),
                'context' => json_encode(['backfilled' => true, 'seeded_current_state' => true]),
                'happened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 22,
                'event_uuid' => '00000000-0000-0000-0000-000000000022',
                'correlation_id' => '10000000-0000-0000-0000-000000000022',
                'aggregate_type' => 'order_item',
                'aggregate_id' => 302,
                'order_item_no' => 302,
                'order_no' => 'S-7002',
                'work_order_no' => 802,
                'event_type' => 'work_order_created_single',
                'event_group' => 'create',
                'source_screen' => 'Siparis Yonetimi',
                'source_action' => 'Is Emri Ver',
                'actor_type' => 'admin',
                'actor_id' => '1',
                'actor_name' => 'Admin User',
                'status_before' => 'UretimBekliyor',
                'status_after' => 'IsEmriVerildi',
                'title_human' => 'Siparise is emri verildi',
                'summary_human' => 'Admin User siparise is emri verdi.',
                'next_step_human' => 'Havuz bekleniyor.',
                'payload_before' => json_encode(['Durum' => 'UretimBekliyor']),
                'payload_after' => json_encode(['Durum' => 'IsEmriVerildi']),
                'context' => json_encode([]),
                'happened_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('work_order_snapshots')->insert([
            [
                'aggregate_type' => 'order_item',
                'aggregate_id' => 301,
                'order_item_no' => 301,
                'order_no' => 'S-7001',
                'work_order_no' => 801,
                'current_status' => 'IsEmriVerildi',
                'current_stage' => 'issued',
                'current_holder_name' => 'Dikim',
                'next_expected_action' => 'Havuz bekleniyor.',
                'alert_count' => 0,
                'snapshot' => json_encode(['alerts' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aggregate_type' => 'order_item',
                'aggregate_id' => 302,
                'order_item_no' => 302,
                'order_no' => 'S-7002',
                'work_order_no' => 802,
                'current_status' => 'IsEmriVerildi',
                'current_stage' => 'issued',
                'current_holder_name' => 'Dikim',
                'next_expected_action' => 'Havuz bekleniyor.',
                'alert_count' => 0,
                'snapshot' => json_encode(['alerts' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/feed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_no', 'S-7002');

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/feed?per_page=1&page=2&include_seeded=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/order_item/301')
            ->assertOk()
            ->assertJsonPath('data.insights.backfilled_count', 1)
            ->assertJsonPath('data.insights.seeded_count', 1)
            ->assertJsonFragment(['message' => 'Bazi eski adimlarda islemi yapan kisi bilgisi gorunmuyor.'])
            ->assertJsonFragment(['message' => 'Bazi eski adimlarda islemin hangi ekrandan yapildigi gorunmuyor.']);
    }

    public function test_snapshot_alerts_are_visible_even_without_timeline_events(): void
    {
        $this->createWorkOrderCenterTables();
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createCriticalStockTables();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();

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
            'No' => 41,
            'SiparisNo' => 'S-9001',
            'Musteri' => 'Legacy Musteri',
            'UrunAdi' => 'Puf Zem',
            'Adet' => 1,
            'Durum' => 'StokKarsilandi',
            'Aktif' => 1,
            'EslesenUrunNo' => 7,
            'EslesenUrunTur' => 'Nihai',
            'BagliOlduguOzelUretimNo' => 77,
            'GorevNo' => 901,
        ]);

        app(\App\Services\WorkOrderSnapshotProjector::class)->projectOrderItem(41);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/order_item/41')
            ->assertOk()
            ->assertJsonPath('data.entity.alert_count', 2)
            ->assertJsonFragment(['message' => 'Kayit stoktan kapanmis ama GIED baglantisi hala dolu gorunuyor.'])
            ->assertJsonFragment(['message' => 'Kayit stoktan karsilanmis gorunuyor ama ilgili event kaydi bulunamadi.']);
    }

    public function test_work_order_snapshot_detects_orphan_and_department_anomalies(): void
    {
        $this->createWorkOrderCenterTables();
        $this->createLegacyWorkOrdersTable();

        DB::table('tbGorevler')->insert([
            'No' => 950,
            'SiparisSatirNo' => null,
            'SiparisNo' => null,
            'ToplamAdet' => 0,
            'BolumAdiNo' => null,
        ]);

        app(\App\Services\WorkOrderSnapshotProjector::class)->projectWorkOrder(950);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/work-order-center/entity/work_order/950')
            ->assertOk()
            ->assertJsonPath('data.entity.alert_count', 4)
            ->assertJsonFragment(['message' => 'Is emri kaydinin bagli siparis satiri bulunamadi.'])
            ->assertJsonFragment(['message' => 'Is emri uzerinde bolum bilgisi eksik gorunuyor.'])
            ->assertJsonFragment(['message' => 'Is emri toplam adedi sifir veya negatif gorunuyor.'])
            ->assertJsonFragment(['message' => 'Bu is emri icin merkezde olay kaydi bulunamadi.']);
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
}
