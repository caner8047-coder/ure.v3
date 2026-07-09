<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class AdminOperationalResetTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_admin_reset_requires_confirmation_token(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/admin/reset-test-data', [])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Sıfırlama onayı eksik.',
            ]);
    }

    public function test_admin_reset_clears_operational_data_and_restores_order_stock(): void
    {
        $this->createResetTables();

        DB::table('tbBolumAraStok')->insert([
            'No' => 1,
            'BolumAdiNo' => 2,
            'Adet' => 4,
            'AraUrunAdiNo' => 10,
            'UrunIDNo' => 20,
            'TamponMiktar' => 2,
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 2,
            'BolumAdiNo' => 2,
            'Adet' => 10,
            'AraUrunAdiNo' => 11,
            'UrunIDNo' => 20,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 3,
            'BolumAdiNo' => 4,
            'Adet' => 6,
            'AraUrunAdiNo' => 26,
            'UrunIDNo' => 20,
            'TamponMiktar' => 6,
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 4,
            'Adet' => 6,
            'AraUrunAdiNo' => 27,
            'UrunIDNo' => 20,
            'TamponMiktar' => 6,
        ]);

        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) Str::uuid(),
            'stock_row_no' => 1,
            'component_no' => 10,
            'department_no' => 2,
            'product_no' => 20,
            'movement_type' => 'order_stock_out',
            'direction' => 'out',
            'title_human' => 'Siparis icin stok cikisi',
            'quantity_before' => 6,
            'quantity_delta' => -2,
            'quantity_after' => 4,
            'buffer_before' => 4,
            'buffer_delta' => -2,
            'buffer_after' => 2,
            'source_type' => 'order_item',
            'source_id' => '1',
            'order_item_no' => 1,
            'order_no' => 'TEST-1',
            'actor_type' => 'admin',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) Str::uuid(),
            'stock_row_no' => 3,
            'component_no' => 26,
            'department_no' => 4,
            'product_no' => 20,
            'movement_type' => 'production_stock_in',
            'direction' => 'in',
            'title_human' => 'Uretimden stok girisi',
            'quantity_before' => 5,
            'quantity_delta' => 1,
            'quantity_after' => 6,
            'buffer_before' => 5,
            'buffer_delta' => 0,
            'buffer_after' => 5,
            'source_type' => 'personnel_task',
            'source_id' => '1',
            'personnel_task_no' => 1,
            'actor_type' => 'personnel',
            'actor_id' => '53',
            'actor_name' => 'Adem',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) Str::uuid(),
            'stock_row_no' => 4,
            'component_no' => 27,
            'department_no' => 4,
            'product_no' => 20,
            'movement_type' => 'production_stock_in',
            'direction' => 'in',
            'title_human' => 'Uretimden stok girisi',
            'quantity_before' => 6,
            'quantity_delta' => 2,
            'quantity_after' => 8,
            'buffer_before' => 8,
            'buffer_delta' => 0,
            'buffer_after' => 8,
            'source_type' => 'personnel_task',
            'source_id' => '2',
            'personnel_task_no' => 2,
            'actor_type' => 'personnel',
            'actor_id' => '53',
            'actor_name' => 'Adem',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'movement_uuid' => (string) Str::uuid(),
            'stock_row_no' => 4,
            'component_no' => 27,
            'department_no' => 4,
            'product_no' => 20,
            'movement_type' => 'production_stock_in_reversed',
            'direction' => 'out',
            'title_human' => 'Uretim stok girisi geri alindi',
            'quantity_before' => 8,
            'quantity_delta' => -2,
            'quantity_after' => 6,
            'buffer_before' => 8,
            'buffer_delta' => 0,
            'buffer_after' => 8,
            'source_type' => 'work_order_cancel',
            'source_id' => '1',
            'personnel_task_no' => 2,
            'actor_type' => 'admin',
            'actor_id' => '4',
            'actor_name' => 'Admin',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tbSiparisSatir')->insert([
            'No' => 1,
            'SiparisNo' => 'TEST-1',
            'UrunAdi' => 'Test Urun',
            'Adet' => 2,
            'Durum' => 'StokKarsilandi',
            'Aktif' => 1,
        ]);
        DB::table('tbBolumHavuz')->insert(['No' => 1, 'UrunIDNo' => 20, 'Adet' => 2]);
        DB::table('tbPersonelGorev')->insert(['No' => 1, 'UrunIDNo' => 20, 'PersonelNo' => 1, 'Adet' => 2]);
        DB::table('tbGorevler')->insert(['No' => 1, 'UrunIDNo' => 20, 'ToplamAdet' => 2]);
        DB::table('tbIsEmriGecmisi')->insert([
            'SiparisSatirNo' => 1,
            'SiparisNo' => 'TEST-1',
            'IslemTipi' => 'StoktanDusuldu',
            'IslemTarihi' => now(),
        ]);
        DB::table('tbVerilenGorevler')->insert(['No' => 1, 'ToplamAdet' => 2]);
        DB::table('work_order_events')->insert([
            'event_uuid' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'aggregate_type' => 'order_item',
            'aggregate_id' => 1,
            'order_item_no' => 1,
            'event_type' => 'stock_deducted',
            'event_group' => 'stock',
            'title_human' => 'Siparis stoktan karsilandi',
            'summary_human' => 'Test event',
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('work_order_snapshots')->insert([
            'aggregate_type' => 'order_item',
            'aggregate_id' => 1,
            'order_item_no' => 1,
            'current_status' => 'StokKarsilandi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tbKritikStokUyari')->insert([
            'AraUrunAdiNo' => 10,
            'EsikMiktar' => 5,
            'MevcutStok' => 4,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/admin/reset-test-data', [
                'confirmation' => 'RESET_TEST_DATA',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stock.order_stock_quantity_restored', 2)
            ->assertJsonPath('data.stock.production_stock_quantity_removed', 1);

        foreach ([
            'tbSiparisSatir',
            'tbBolumHavuz',
            'tbPersonelGorev',
            'tbGorevler',
            'tbIsEmriGecmisi',
            'tbVerilenGorevler',
            'work_order_events',
            'work_order_snapshots',
            'stock_movements',
            'tbKritikStokUyari',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} temizlenmedi.");
        }

        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 1,
            'Adet' => 6,
            'TamponMiktar' => 4,
        ]);
        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 2,
            'Adet' => 10,
            'TamponMiktar' => 0,
        ]);
        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 3,
            'Adet' => 5,
            'TamponMiktar' => 5,
        ]);
        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 4,
            'Adet' => 6,
            'TamponMiktar' => 6,
        ]);
    }

    private function createResetTables(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->createWorkOrderCenterTables();
        $this->createCriticalStockTables();

        Schema::create('tbVerilenGorevler', function (Blueprint $table) {
            $table->increments('No');
            $table->integer('ToplamAdet')->nullable()->default(0);
        });
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
