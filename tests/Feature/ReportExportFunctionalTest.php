<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class ReportExportFunctionalTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();

        // Migrate Spatie permission tables
        $migration = require database_path('migrations/2026_07_10_000808_create_permission_tables.php');
        $migration->up();

        // Seed roles & permissions for testing
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Permission::create(['name' => 'view stocks']);
        \Spatie\Permission\Models\Permission::create(['name' => 'view planning']);
        \Spatie\Permission\Models\Role::create(['name' => 'Üst Yönetim']);

        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyStocksTable();

        $this->seedDatabase();
    }

    public function test_unauthorized_user_cannot_export_stocks_report(): void
    {
        $user = $this->makeRegularUser(); // No permissions

        $response = $this->actingAs($user)
            ->get('/api/reports/stocks/export?format=csv');

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_export_production_report(): void
    {
        $user = $this->makeRegularUser(); // No permissions

        $response = $this->actingAs($user)
            ->get('/api/reports/production/export?format=csv');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_export_stocks_csv(): void
    {
        $user = $this->makeAdminUser();
        // Spatie permissions are skipped in test trait or handled
        $user->givePermissionTo('view stocks');

        $response = $this->actingAs($user)
            ->get('/api/reports/stocks/export?format=csv');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Bölüm;Ara Ürün No;Ara Ürün Adı;Sistem Kodu;Toplam Adet;Tampon Miktarı;Boşta Adet;Durum', $content);
        $this->assertStringContainsString('Döşeme', $content);
        $this->assertStringContainsString('DÖŞ/berjer zem alaves', $content);
    }

    public function test_authorized_user_can_export_stocks_html(): void
    {
        $user = $this->makeAdminUser();
        $user->givePermissionTo('view stocks');

        $response = $this->actingAs($user)
            ->get('/api/reports/stocks/export?format=html');

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('<title>Mevcut Stok Durumu Raporu</title>', $content);
        $this->assertStringContainsString('DÖŞ/berjer zem alaves', $content);
    }

    public function test_authorized_user_can_export_production_csv(): void
    {
        $user = $this->makeAdminUser();
        $user->givePermissionTo('view planning');

        $response = $this->actingAs($user)
            ->get('/api/reports/production/export?format=csv');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Görev No;Tarih;Personel Adı;Bölüm;Ara Ürün Adı;Hazır Adet;Bekleyen Adet;Durum', $content);
        $this->assertStringContainsString('abdullah kasimi', $content);
        $this->assertStringContainsString('DÖŞ/berjer zem alaves', $content);
    }

    public function test_authorized_user_can_export_production_html(): void
    {
        $user = $this->makeAdminUser();
        $user->givePermissionTo('view planning');

        $response = $this->actingAs($user)
            ->get('/api/reports/production/export?format=html');

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('<title>Aktif Üretim Planlama & Görev Raporu</title>', $content);
        $this->assertStringContainsString('abdullah kasimi', $content);
    }

    private function seedDatabase(): void
    {
        DB::table('tbBolum')->insert([
            'No' => 2,
            'BolumAdi' => 'Döşeme',
        ]);

        DB::table('tbUrunler')->insert([
            'No' => 20,
            'UrunID' => 'berjer zem alaves',
            'AraAdlarYol' => '',
            'SistemAdi' => 'berjer zem alaves',
            'SistemKodu' => 'BRJ',
        ]);

        DB::table('tbAraUrun')->insert([
            'No' => 7,
            'AraUrunAdi' => 'DÖŞ/berjer zem alaves',
            'Performans' => 0,
            'BolumAdiNo' => 2,
            'MinAdet' => 0,
            'UrunCesidi' => 'Ara',
            'Yol' => '',
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'UrunIDNo' => 20,
            'Adet' => 10,
            'TamponMiktar' => 5,
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 1,
            'Ad' => 'abdullah',
            'Soyad' => 'kasimi',
            'BolumAdiNo' => 2,
            'Mail' => 'abdullah@example.com',
            'Telefon' => '',
            'Adres' => '',
            'Sifre' => '',
        ]);

        DB::table('tbPersonelGorev')->insert([
            'No' => 5,
            'UrunIDNo' => 20,
            'PersonelNo' => 1,
            'GorevBaslamaTarihi' => '11/05/2026 21:01',
            'Adet' => 2,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'AraUrunAdiNo' => 7,
            'BolumAdiNo' => 2,
            'SiparisSatirNo' => null,
            'SiparisNo' => null,
        ]);
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

        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $user->assignRole('Üst Yönetim');
        }

        return $user;
    }

    private function makeRegularUser(): User
    {
        $user = new User();
        $user->guard_name = 'web';
        $user->forceFill([
            'id' => 2,
            'name' => 'Regular',
            'surname' => 'User',
            'email' => 'user@example.com',
            'password' => 'secret',
            'department_id' => 2,
            'personnel_no' => 2,
        ]);

        return $user;
    }
}
