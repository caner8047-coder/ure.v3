<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class PersonnelTaskReportExportTest extends TestCase
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
        $this->seedPersonnelTaskReportRows();
    }

    public function test_personnel_task_report_returns_date_based_task_rows(): void
    {
        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/reports/tasks?report=personnel-tasks&sort_by=gr.No&sort_dir=asc');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('total_adet', 2)
            ->assertJsonPath('total_bekleyen', 0)
            ->assertJsonPath('data.0.No', 5)
            ->assertJsonPath('data.0.TamAd', 'abdullah kasimi')
            ->assertJsonPath('data.0.UrunID', 'berjer zem alaves')
            ->assertJsonPath('data.0.AraUrunAdi', 'DÖŞ/berjer zem alaves')
            ->assertJsonPath('data.0.GorevBaslamaTarihi', '11/05/2026 21:01')
            ->assertJsonPath('data.0.Durum', 'Üretimde');
    }

    public function test_personnel_task_export_downloads_real_xlsx_like_legacy_tasks_file(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required to inspect the generated XLSX.');
        }

        $response = $this->actingAs($this->makeAdminUser())
            ->get('/api/reports/tasks-export?report=personnel-tasks&sort_by=gr.No&sort_dir=asc');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->getContent();
        $this->assertStringStartsWith('PK', $content);

        $tmp = tempnam(sys_get_temp_dir(), 'personnel_tasks_xlsx_');
        file_put_contents($tmp, $content);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $stylesXml = $zip->getFromName('xl/styles.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertIsString($workbookXml);
        $this->assertIsString($sheetXml);
        $this->assertIsString($stylesXml);
        $this->assertStringContainsString('name="Görevler"', $workbookXml);
        $this->assertStringContainsString('Personel Adı', $sheetXml);
        $this->assertStringContainsString('Ürün Adı', $sheetXml);
        $this->assertStringContainsString('Ara Ürün', $sheetXml);
        $this->assertStringContainsString('Görev Başlangıç Tarihi', $sheetXml);
        $this->assertStringContainsString('Toplam Adet', $sheetXml);
        $this->assertStringContainsString('Bekleyen Adet', $sheetXml);
        $this->assertStringContainsString('abdullah kasimi', $sheetXml);
        $this->assertStringContainsString('berjer zem alaves', $sheetXml);
        $this->assertStringContainsString('DÖŞ/berjer zem alaves', $sheetXml);
        $this->assertStringContainsString('FF4B3621', $stylesXml);
    }

    private function seedPersonnelTaskReportRows(): void
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
