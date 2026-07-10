<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();

        // Migrate personal_access_tokens and users tables
        $tokensMigration = require database_path('migrations/2026_07_11_012426_create_personal_access_tokens_table.php');
        $tokensMigration->up();

        $usersMigration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $usersMigration->up();

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
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyMessagesTable();

        // Seed data
        DB::table('tbBolum')->insert([
            ['No' => 1, 'BolumAdi' => 'Uretim'],
        ]);

        DB::table('tbUrunler')->insert([
            ['No' => 17, 'UrunID' => 'Meyra Kanepe', 'SistemAdi' => 'Meyra Kanepe'],
        ]);

        DB::table('tbPersonel')->insert([
            ['PersonelNo' => 5, 'Ad' => 'Ahmet', 'Soyad' => 'Kaya', 'Mail' => 'ahmet@example.com', 'BolumAdiNo' => 1],
        ]);
    }

    public function test_mobile_login_issues_token_and_unauthorized_endpoints_are_blocked(): void
    {
        // Create user
        $user = User::factory()->create([
            'email' => 'ahmet@example.com',
            'password' => bcrypt('password123'),
            'personnel_no' => 5,
        ]);

        // Attempt login
        $response = $this->postJson('/api/login', [
            'email' => 'ahmet@example.com',
            'password' => 'password123',
            'device_name' => 'iPhone'
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user']);

        $token = $response->json('token');

        // Check blocked endpoint without token
        $this->getJson('/api/tasks')->assertStatus(401);

        // Check authorized endpoint with token
        $responseTasks = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/tasks');

        $responseTasks->assertOk();
    }

    public function test_mobile_task_completion_records_quantities_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'ahmet@example.com',
            'password' => bcrypt('password123'),
            'personnel_no' => 5,
        ]);

        // Insert task for worker
        DB::table('tbPersonelGorev')->insert([
            'No' => 101,
            'PersonelNo' => 5,
            'UrunIDNo' => 17,
            'AraUrunAdiNo' => 0,
            'BolumAdiNo' => 1,
            'Adet' => 10,
            'BekleyenAdet' => 0,
            'GorevBaslamaTarihi' => '2026-07-11 10:00:00',
        ]);

        $token = $user->createToken('Mobile')->plainTextToken;

        // Complete 4 items
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/tasks/101/progress', [
                'completed_qty' => 4
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verify quantity reduced to 6
        $this->assertDatabaseHas('tbPersonelGorev', [
            'No' => 101,
            'Adet' => 6,
        ]);

        // Complete remaining 6 items
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/tasks/101/progress', [
                'completed_qty' => 6
            ]);

        $response2->assertOk()
            ->assertJsonPath('completed_all', true);

        // Verify task deleted from active tasks table
        $this->assertDatabaseMissing('tbPersonelGorev', [
            'No' => 101
        ]);

        // Verify inserted into completed tasks history table
        $this->assertDatabaseHas('tbGorevler', [
            'PersonelNo' => 5,
            'UrunIDNo' => 17,
            'ToplamAdet' => 6,
        ]);
    }
}
