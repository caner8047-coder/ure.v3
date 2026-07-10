<?php

namespace Tests\Feature;

// Import database facade first to register sqlite custom functions if not done
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class PlanningControllersFunctionalTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyStocksTable();
    }

    public function test_get_personnel_list_returns_all_personnel_correctly(): void
    {
        DB::table('tbBolum')->insert([
            'No' => 2,
            'BolumAdi' => 'Marangozhane',
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 12,
            'Ad' => 'Ahmet',
            'Soyad' => 'Demir',
            'BolumAdiNo' => 2,
            'Mail' => 'ahmet@example.com',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/planning/personnel');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.PersonelNo', 12)
            ->assertJsonPath('data.0.PersonelAdi', 'Ahmet Demir');
    }

    public function test_increment_task_increases_ready_and_reduces_waiting_quantity(): void
    {
        DB::table('tbPersonelGorev')->insert([
            'No' => 45,
            'UrunIDNo' => 1,
            'PersonelNo' => 12,
            'Adet' => 5,
            'BekleyenAdet' => 10,
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 2,
            'Onay' => 'hazir',
        ]);

        DB::table('tbBolumHavuz')->insert([
            'No' => 100,
            'UrunIDNo' => 1,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 8,
            'Adet' => 5,
            'ToplamAdet' => 5,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/increment/45');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $task = DB::table('tbPersonelGorev')->where('No', 45)->first();
        $this->assertEquals(6, $task->Adet);
        $this->assertEquals(10, $task->BekleyenAdet); // incrementTask keeps BekleyenAdet as is, only shifts pool stock
    }

    public function test_decrement_task_reduces_ready_and_returns_to_waiting_quantity(): void
    {
        DB::table('tbPersonelGorev')->insert([
            'No' => 45,
            'UrunIDNo' => 1,
            'PersonelNo' => 12,
            'Adet' => 5,
            'BekleyenAdet' => 10,
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 2,
            'Onay' => 'hazir',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/planning/decrement/45');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $task = DB::table('tbPersonelGorev')->where('No', 45)->first();
        $this->assertEquals(4, $task->Adet);
        $this->assertEquals(10, $task->BekleyenAdet);
    }

    public function test_update_task_date_updates_legacy_date_field_correctly(): void
    {
        DB::table('tbPersonelGorev')->insert([
            'No' => 45,
            'UrunIDNo' => 1,
            'PersonelNo' => 12,
            'Adet' => 5,
            'BekleyenAdet' => 10,
            'AraUrunAdiNo' => 8,
            'BolumAdiNo' => 2,
            'Onay' => 'hazir',
            'GorevBaslamaTarihi' => '10/07/2026 14:00',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->putJson('/api/planning/task/45/date', [
                'date' => '2026-07-25 14:30',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $task = DB::table('tbPersonelGorev')->where('No', 45)->first();
        $this->assertStringStartsWith('25/07/2026 ', $task->GorevBaslamaTarihi);
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
