<?php

namespace Tests\Feature;

use App\Events\CriticalStockAlert;
use App\Events\NewNotification;
use App\Events\TaskAssigned;
use App\Events\WorkOrderStatusChanged;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StockMovementLogger;
use App\Services\WorkOrderEventLogger;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class BroadcastEventTest extends TestCase
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

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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

        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Bileşen A', 'BolumAdiNo' => 2, 'MinAdet' => 5],
        ]);
    }

    public function test_work_order_status_change_dispatches_websocket_broadcast(): void
    {
        Event::fake([WorkOrderStatusChanged::class]);

        $logger = app(WorkOrderEventLogger::class);
        $logger->log([
            'order_item_no' => 123,
            'work_order_no' => 456,
            'status_before' => 'UretimBekliyor',
            'status_after' => 'Uretimde',
            'event_type' => 'status_changed',
            'title_human' => 'Durum Değişti',
            'summary_human' => 'İş emri üretime alındı.',
            'context' => ['department_id' => 1],
        ]);

        Event::assertDispatched(WorkOrderStatusChanged::class, function ($event) {
            return $event->orderItemNo === 123
                && $event->workOrderNo === 456
                && $event->statusBefore === 'UretimBekliyor'
                && $event->statusAfter === 'Uretimde'
                && $event->departmentId === 1;
        });
    }

    public function test_critical_stock_movement_dispatches_critical_stock_alert(): void
    {
        Event::fake([CriticalStockAlert::class]);

        // Insert a critical stock threshold
        DB::table('tbKritikStokEsik')->insert([
            'AraUrunAdiNo' => 10,
            'EsikMiktar' => 10,
            'Aktif' => 1,
            'OtomatikIsEmri' => 0,
            'IsEmriAdet' => 0,
        ]);

        $logger = app(StockMovementLogger::class);

        // Simulate stock drop from 12 to 8 (which is below the threshold of 10)
        $logger->logChange(
            ['No' => 50, 'AraUrunAdiNo' => 10, 'BolumAdiNo' => 2, 'Adet' => 12],
            ['No' => 50, 'AraUrunAdiNo' => 10, 'BolumAdiNo' => 2, 'Adet' => 8]
        );

        Event::assertDispatched(CriticalStockAlert::class, function ($event) {
            return $event->componentNo === 10
                && $event->departmentNo === 2
                && $event->currentQuantity === 8
                && $event->thresholdQuantity === 10
                && $event->quantityDelta === -4;
        });
    }

    public function test_task_assignment_dispatches_task_assigned_event(): void
    {
        Event::fake([TaskAssigned::class]);

        $logger = app(WorkOrderEventLogger::class);
        $logger->log([
            'event_type' => 'task_assigned_by_admin',
            'personnel_task_no' => 888,
            'title_human' => 'Görev Atandı',
            'summary_human' => 'Personele yeni montaj görevi atandı.',
            'payload_after' => [
                'No' => 888,
                'PersonelNo' => 5,
                'AraUrunAdiNo' => 10,
                'BolumAdiNo' => 2,
                'Adet' => 100,
            ],
        ]);

        Event::assertDispatched(TaskAssigned::class, function ($event) {
            return $event->taskNo === 888
                && $event->personnelNo === 5
                && $event->departmentId === 2
                && $event->taskDescription === 'Personele yeni montaj görevi atandı.';
        });
    }

    public function test_user_notification_dispatches_new_notification_event(): void
    {
        Event::fake([NewNotification::class]);

        $user = User::factory()->create([
            'id' => 99,
            'name' => 'Caner',
            'email' => 'caner@test.com',
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->sendToUser($user, 'Test Başlığı', 'Test Bildirim İçeriği', ['broadcast']);

        Event::assertDispatched(NewNotification::class, function ($event) {
            return $event->userId === 99
                && $event->title === 'Test Başlığı'
                && $event->message === 'Test Bildirim İçeriği';
        });
    }
}
