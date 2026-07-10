<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class OrderControllersFunctionalTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createGiedAllocationTable();
        $this->createCriticalStockTables();
        $this->createOzelUretimTable();
    }

    public function test_match_product_caches_match_successfully(): void
    {
        DB::table('tbUrunler')->insert([
            'No' => 10,
            'UrunID' => 'Puf A',
            'SistemAdi' => 'Puf A Sistem',
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 5,
            'UrunAdi' => 'Puf A Excel',
            'Adet' => 1,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=matchProduct', [
                'siparisSatirNo' => 5,
                'eslesenUrunNo' => 10,
                'eslesenUrunTur' => 'Nihai',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $row = DB::table('tbSiparisSatir')->where('No', 5)->first();
        $this->assertEquals(10, $row->EslesenUrunNo);
        $this->assertEquals('Nihai', $row->EslesenUrunTur);
    }

    public function test_clear_order_match_removes_association(): void
    {
        DB::table('tbSiparisSatir')->insert([
            'No' => 5,
            'UrunAdi' => 'Puf A Excel',
            'EslesenUrunNo' => 10,
            'EslesenUrunTur' => 'Nihai',
            'EslesmePuani' => 100,
            'EslesmeYontemi' => 'Manuel',
            'Aktif' => 1,
            'Durum' => 'UretimBekliyor',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=clearOrderMatch', [
                'siparisSatirNo' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $row = DB::table('tbSiparisSatir')->where('No', 5)->first();
        $this->assertNull($row->EslesenUrunNo);
        $this->assertNull($row->EslesenUrunTur);
        $this->assertNull($row->EslesmePuani);
    }

    public function test_save_threshold_sets_critical_stock_minimum(): void
    {
        DB::table('tbAraUrun')->insert([
            'No' => 8,
            'AraUrunAdi' => 'Masa Ayagi',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=saveThreshold', [
                'araUrunAdiNo' => 8,
                'esikMiktar' => 15,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $threshold = DB::table('tbKritikStokEsik')
            ->where('AraUrunAdiNo', 8)
            ->first();

        $this->assertNotNull($threshold);
        $this->assertEquals(15, $threshold->EsikMiktar);
    }

    public function test_link_order_to_special_production_allocates_correctly(): void
    {
        DB::table('tbOzelUretim')->insert([
            'No' => 202,
            'UrunIDNo' => 99,
            'Aciklama' => 'Puf A Batch',
            'Aktif' => 1,
            'Durum' => 'DevamEden',
            'Adet' => 10,
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 101,
            'SiparisNo' => 'SIP-101',
            'UrunAdi' => 'Puf A',
            'Adet' => 2,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
            'EslesenUrunNo' => 99,
            'EslesenUrunTur' => 'Nihai',
            'Musteri' => null,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/SiparisApi.ashx?action=linkOrderToSpecialProduction', [
                'siparisSatirNo' => 101,
                'ozelUretimNo' => 202,
                'quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $row = DB::table('tbSiparisSatir')->where('No', 101)->first();
        $this->assertEquals(202, $row->BagliOlduguOzelUretimNo);
    }

    private function createOzelUretimTable(): void
    {
        Schema::create('tbOzelUretim', function ($table) {
            $table->integer('No')->primary();
            $table->integer('UrunIDNo')->nullable();
            $table->string('Aciklama', 255)->nullable();
            $table->string('BaslangicTarihi', 50)->nullable();
            $table->boolean('Aktif')->default(true);
            $table->string('Durum', 50)->nullable();
            $table->integer('Adet')->default(0);
        });
    }

    private function makeAdminUser(): User
    {
        $user = new User();
        $user->guard_name = 'web';
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
