<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BomService;
use Illuminate\Support\Facades\DB;
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

    public function test_deduct_stock_clears_special_production_binding_for_reserved_order(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
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
            'BagliOlduguOzelUretimNo' => null,
        ]);

        $this->assertSame(2, intval(DB::table('tbBolumAraStok')->value('Adet')));
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

    public function test_planning_increment_consumes_pool_and_increases_pending_quantity(): void
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
        $bomService->shouldReceive('traceContextFromRecord')->once()->andReturn([]);
        $bomService->shouldReceive('scopeQueryToTrace')->once()->andReturnUsing(fn ($query) => $query);
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
            'BekleyenAdet' => 3,
        ]);

        $this->assertDatabaseHas('tbBolumHavuz', [
            'No' => 7,
            'Adet' => 4,
            'ToplamAdet' => 4,
        ]);
    }

    public function test_personnel_task_delete_returns_only_pending_quantity_to_pool(): void
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
                    && $adet === 2
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
            ->assertJsonPath('message', 'Görev silindi ve 2 adet havuza geri aktarıldı.');

        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 9]);
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

    private function makePersonnelUser(): User
    {
        $user = new User();
        $user->forceFill([
            'id' => 22,
            'name' => 'Personel',
            'surname' => 'User',
            'email' => 'personel@example.com',
            'password' => 'secret',
            'department_id' => 3,
            'personnel_no' => 22,
        ]);

        return $user;
    }
}
