<?php

namespace Tests\Feature;

use App\Events\ForecastCompleted;
use App\Models\DemandForecastSnapshot;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\ForecastTrainingDataBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class ForecastServiceTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();

        // Migrate permissions
        $migration = require database_path('migrations/2026_07_10_000808_create_permission_tables.php');
        $migration->up();

        // Migrate users table
        $usersMigration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $usersMigration->up();

        // Migrate demand_forecast_snapshots table
        $forecastMigration = require database_path('migrations/2026_07_11_020000_create_demand_forecast_snapshots_table.php');
        $forecastMigration->up();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::create(['name' => 'Üst Yönetim', 'guard_name' => 'web']);

        // Initialize schema tables
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();
        $this->createWorkOrderCenterTables();
        $this->createCriticalStockTables();
        $this->createLegacyMessagesTable();

        // Create standard roles/permissions if any
        DB::table('tbBolum')->insert([
            ['No' => 1, 'BolumAdi' => 'Planlama'],
            ['No' => 2, 'BolumAdi' => 'Depo'],
        ]);

        DB::table('tbUrunler')->insert([
            ['No' => 17, 'UrunID' => 'Meyra Kanepe', 'SistemAdi' => 'Meyra Kanepe'],
        ]);

        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Bileşen A', 'BolumAdiNo' => 2, 'MinAdet' => 5],
        ]);
    }

    public function test_training_data_builder_aggregates_historical_demands(): void
    {
        // Insert historical orders for matched product
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisTarihi' => '2026-01-15 10:00:00',
                'EslesenUrunNo' => 17,
                'EslesenUrunTur' => 'urun',
                'Adet' => 10,
                'Aktif' => 1,
            ],
            [
                'No' => 2,
                'SiparisTarihi' => '2026-01-20 15:30:00',
                'EslesenUrunNo' => 17,
                'EslesenUrunTur' => 'urun',
                'Adet' => 5,
                'Aktif' => 1,
            ],
            [
                'No' => 3,
                'SiparisTarihi' => '2026-02-05 09:00:00',
                'EslesenUrunNo' => 17,
                'EslesenUrunTur' => 'urun',
                'Adet' => 20,
                'Aktif' => 1,
            ]
        ]);

        $builder = app(ForecastTrainingDataBuilder::class);
        $history = $builder->getMonthlyProductHistory(17);

        $this->assertCount(2, $history);
        $this->assertEquals('2026-01-01', $history[0]['ds']);
        $this->assertEquals(15, $history[0]['y']);
        $this->assertEquals('2026-02-01', $history[1]['ds']);
        $this->assertEquals(20, $history[1]['y']);
    }

    public function test_forecast_service_stores_snapshots_and_broadcasts_completion(): void
    {
        Event::fake([ForecastCompleted::class]);

        // Insert historical data
        DB::table('tbSiparisSatir')->insert([
            ['No' => 1, 'SiparisTarihi' => '2026-01-15', 'EslesenUrunNo' => 17, 'EslesenUrunTur' => 'urun', 'Adet' => 10, 'Aktif' => 1]
        ]);

        // Mock Prophet Microservice HTTP response
        Http::fake([
            'http://forecast:5000/predict' => Http::response([
                'success' => true,
                'predictions' => [
                    [
                        'ds' => '2026-02-01',
                        'yhat' => 15.5,
                        'yhat_lower' => 12.0,
                        'yhat_upper' => 19.0,
                        'mape' => 0.05
                    ]
                ],
                'mape' => 0.05,
                'method' => 'prophet'
            ], 200)
        ]);

        $service = app(ForecastService::class);
        $result = $service->forecastProduct(17, 'monthly', 1);

        $this->assertTrue($result['success']);
        
        $this->assertDatabaseHas('demand_forecast_snapshots', [
            'product_id' => 17,
            'forecasted_demand' => 15.5,
            'status' => 'predicted'
        ]);

        Event::assertDispatched(ForecastCompleted::class, function ($event) {
            return $event->targetId === 17
                && $event->targetType === 'urun'
                && $event->periodType === 'monthly';
        });
    }

    public function test_forecast_controller_actions_approve_and_override_snapshots(): void
    {
        $admin = User::factory()->create([
            'id' => 1,
            'name' => 'Admin User',
            'department_id' => null
        ]);
        $admin->assignRole('Üst Yönetim'); // Ensure user has permissions or isAdmin returns true

        // Seed Spatie role permissions for standard routes
        \Spatie\Permission\Models\Permission::create(['name' => 'view planning', 'guard_name' => 'web']);
        $admin->givePermissionTo('view planning');

        // Create snapshot
        $snapshot = DemandForecastSnapshot::create([
            'product_id' => 17,
            'period_date' => '2026-02-01',
            'period_type' => 'monthly',
            'forecasted_demand' => 25.0,
            'status' => 'predicted'
        ]);

        $snapshotId = $snapshot->id;

        // Approve
        $response = $this->actingAs($admin)
            ->postJson("/api/forecast/approve/{$snapshotId}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('approved', $snapshot->fresh()->status);

        // Override
        $response = $this->actingAs($admin)
            ->postJson("/api/forecast/override/{$snapshotId}", [
                'override_value' => 35
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('overridden', $snapshot->fresh()->status);
        $this->assertEquals(35, $snapshot->fresh()->override_value);
    }
}
