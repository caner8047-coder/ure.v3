<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class StockLedgerAnalysisTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_stock_movements_explain_current_quantity_free_and_reserved_balance(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();
        $this->createLegacyOrdersTable();

        DB::table('tbBolum')->insert(['No' => 3, 'BolumAdi' => 'Marangozhane']);
        DB::table('tbAraUrun')->insert([
            'No' => 6368,
            'AraUrunAdi' => 'MAR/favela.pavia.bahama berjer kasa(SIRT)',
            'BolumAdiNo' => 3,
            'UrunCesidi' => 'Ara Mamül',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 11253,
            'BolumAdiNo' => 3,
            'AraUrunAdiNo' => 6368,
            'UrunIDNo' => 0,
            'Adet' => 128,
            'TamponMiktar' => 89,
        ]);
        DB::table('tbSiparisSatir')->insert([
            'No' => 271,
            'SiparisNo' => 'STOK-20260514-5820',
            'Musteri' => 'ÖZEL ÜRETİM (Stok İlavesi)',
            'UrunAdi' => 'Berjer kasa',
            'Adet' => 50,
            'Durum' => 'IsEmriVerildi',
            'Aktif' => 1,
            'EslesenUrunNo' => 6368,
            'EslesenUrunTur' => 'Ara',
            'GorevNo' => 77,
        ]);

        DB::table('stock_movements')->insert([
            $this->movementRow(1, '00000000-0000-0000-0000-000000000001', 'work_order_buffer_reserved', 78, 0, 78, 78, -19, 59, [
                'order_item_no' => 271,
                'order_no' => 'STOK-20260514-5820',
                'work_order_no' => 77,
            ]),
            $this->movementRow(2, '00000000-0000-0000-0000-000000000002', 'work_order_buffer_reserved', 78, 0, 78, 59, -20, 39, [
                'order_item_no' => 271,
                'order_no' => 'STOK-20260514-5820',
                'work_order_no' => 77,
            ]),
            $this->movementRow(3, '00000000-0000-0000-0000-000000000003', 'production_stock_in', 78, 12, 90, 39, 12, 51, [
                'order_item_no' => 271,
                'order_no' => 'STOK-20260514-5820',
                'work_order_no' => 77,
                'personnel_task_no' => 901,
                'metadata' => ['reserved_for_order' => false, 'free_stock_production' => true],
            ]),
            $this->movementRow(4, '00000000-0000-0000-0000-000000000004', 'production_stock_in', 90, 38, 128, 51, 38, 89, [
                'order_item_no' => 271,
                'order_no' => 'STOK-20260514-5820',
                'work_order_no' => 77,
                'personnel_task_no' => 902,
                'metadata' => ['reserved_for_order' => false, 'free_stock_production' => true],
            ]),
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks/11253/movements');

        $response
            ->assertOk()
            ->assertJsonPath('analysis.current.quantity', 128)
            ->assertJsonPath('analysis.current.free', 89)
            ->assertJsonPath('analysis.current.reserved', 39)
            ->assertJsonPath('analysis.net.quantity_delta', 50)
            ->assertJsonPath('analysis.net.free_delta', 11)
            ->assertJsonPath('analysis.net.reserved_delta', 39);

        $payload = $response->json();
        $this->assertSame(39, collect($payload['analysis']['reserved_sources'])->sum('reserved_delta'));
        $this->assertTrue(collect($payload['movements'])->contains(function (array $row) {
            return $row['movement_type'] === 'production_stock_in'
                && $row['quantity_delta'] === 38
                && $row['free_delta'] === 38
                && $row['reserved_delta'] === 0
                && str_contains($row['reason_human'], 'boşta stoğa eklendi');
        }));
        $this->assertTrue(collect($payload['movements'])->contains(function (array $row) {
            return $row['movement_type'] === 'work_order_buffer_reserved'
                && $row['buffer_delta'] === -20
                && $row['free_delta'] === -20
                && $row['reserved_delta'] === 20;
        }));
    }

    private function movementRow(
        int $id,
        string $uuid,
        string $type,
        int $quantityBefore,
        int $quantityDelta,
        int $quantityAfter,
        int $bufferBefore,
        int $bufferDelta,
        int $bufferAfter,
        array $overrides = []
    ): array {
        return array_merge([
            'id' => $id,
            'movement_uuid' => $uuid,
            'stock_row_no' => 11253,
            'component_no' => 6368,
            'department_no' => 3,
            'product_no' => 0,
            'movement_type' => $type,
            'direction' => $quantityDelta > 0 ? 'in' : ($bufferDelta < 0 ? 'reserve' : 'neutral'),
            'title_human' => str_replace('_', ' ', $type),
            'quantity_before' => $quantityBefore,
            'quantity_delta' => $quantityDelta,
            'quantity_after' => $quantityAfter,
            'buffer_before' => $bufferBefore,
            'buffer_delta' => $bufferDelta,
            'buffer_after' => $bufferAfter,
            'source_type' => 'work_order_reservation',
            'source_id' => '271',
            'personnel_task_no' => null,
            'actor_type' => 'admin',
            'actor_id' => '1',
            'actor_name' => 'Admin User',
            'description' => 'Test hareketi.',
            'metadata' => json_encode(['context' => $overrides['metadata'] ?? []]),
            'happened_at' => now()->addMinutes($id),
            'created_at' => now(),
            'updated_at' => now(),
        ], array_diff_key($overrides, ['metadata' => true]));
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
