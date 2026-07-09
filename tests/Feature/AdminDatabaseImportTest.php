<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BomService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class AdminDatabaseImportTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_department_import_updates_existing_rows_and_inserts_new_rows(): void
    {
        $this->createLegacyDepartmentsTable();

        DB::table('tbBolum')->insert(['No' => 1, 'BolumAdi' => 'Eski Bolum']);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/departments/import', [
                'rows' => [
                    ['No' => 1, 'Bölüm Adı' => 'Kesim'],
                    ['id' => 2, 'name' => 'Döşeme'],
                    ['name' => 'Paket'],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'inserted' => 2,
                'updated' => 1,
                'skipped' => 0,
            ]);

        $this->assertDatabaseHas('tbBolum', ['No' => 1, 'BolumAdi' => 'Kesim']);
        $this->assertDatabaseHas('tbBolum', ['No' => 2, 'BolumAdi' => 'Döşeme']);
        $this->assertDatabaseHas('tbBolum', ['BolumAdi' => 'Paket']);
    }

    public function test_product_import_accepts_exported_field_names(): void
    {
        $this->createLegacyProductsTable();

        DB::table('tbUrunler')->insert([
            'No' => 10,
            'UrunID' => 'Eski Urun',
            'SistemAdi' => 'Eski Sistem',
            'SistemKodu' => 'OLD',
            'AraAdlarYol' => '',
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/import', [
                'rows' => [
                    [
                        'id' => 10,
                        'name' => 'Yeni Urun',
                        'system_name' => 'Yeni Sistem',
                        'system_code' => 'NEW',
                        'path' => '1-10:2-5',
                    ],
                    [
                        'No' => 11,
                        'UrunID' => 'Yeni Eklenen',
                        'SistemAdi' => 'Yeni Eklenen Sistem',
                        'SistemKodu' => 'ADD',
                        'AraAdlarYol' => '3-1',
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'inserted' => 1,
                'updated' => 1,
                'skipped' => 0,
            ]);

        $this->assertDatabaseHas('tbUrunler', [
            'No' => 10,
            'UrunID' => 'Yeni Urun',
            'SistemAdi' => 'Yeni Sistem',
            'SistemKodu' => 'NEW',
            'AraAdlarYol' => '1-10:2-5',
        ]);
        $this->assertDatabaseHas('tbUrunler', [
            'No' => 11,
            'UrunID' => 'Yeni Eklenen',
            'SistemAdi' => 'Yeni Eklenen Sistem',
            'SistemKodu' => 'ADD',
            'AraAdlarYol' => '3-1',
        ]);
    }

    public function test_stock_csv_import_accepts_semicolon_delimiter_and_header_aliases(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'Döşeme']);
        DB::table('tbAraUrun')->insert(['No' => 7, 'AraUrunAdi' => 'Döşeme Kılıf', 'BolumAdiNo' => 2]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 10,
            'TamponMiktar' => 5,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('sonrakiUrunAdetleriniGuncelle')->once()->with('7', 10, 25);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('7');
        $this->app->instance(BomService::class, $bomService);

        $file = UploadedFile::fake()->createWithContent(
            'stok.csv',
            "\xEF\xBB\xBFStok No;Miktar;Tampon Miktar\n4;25;12\n"
        );

        $response = $this->actingAs($this->makeAdminUser())
            ->post('/api/stocks/import-csv', ['file' => $file], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updated' => 1,
                'errors' => 0,
            ]);

        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 4,
            'Adet' => 25,
            'TamponMiktar' => 12,
        ]);
    }

    public function test_component_bom_path_can_be_updated_without_touching_component_name(): void
    {
        $this->createLegacyComponentsTable();

        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Ana Ürün', 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => '11-1'],
            ['No' => 11, 'AraUrunAdi' => 'Eski Parça', 'UrunCesidi' => 'Ara Mamül', 'Yol' => ''],
            ['No' => 12, 'AraUrunAdi' => 'Yeni Parça', 'UrunCesidi' => 'Ham Madde', 'Yol' => ''],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->putJson('/api/database/components/10/bom-path', [
                'items' => [
                    ['component_id' => 12, 'quantity' => 3],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'path' => '12-3',
            ]);

        $this->assertDatabaseHas('tbAraUrun', [
            'No' => 10,
            'AraUrunAdi' => 'Ana Ürün',
            'Yol' => '12-3',
        ]);
    }

    public function test_component_bom_path_rejects_cycles(): void
    {
        $this->createLegacyComponentsTable();

        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Ana Ürün', 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => '11-1'],
            ['No' => 11, 'AraUrunAdi' => 'Ara Ürün', 'UrunCesidi' => 'Ara Mamül', 'Yol' => '12-1'],
            ['No' => 12, 'AraUrunAdi' => 'Ham Parça', 'UrunCesidi' => 'Ham Madde', 'Yol' => ''],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->putJson('/api/database/components/12/bom-path', [
                'items' => [
                    ['component_id' => 10, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('tbAraUrun', [
            'No' => 12,
            'Yol' => '',
        ]);
    }

    public function test_stock_workbook_import_updates_rows_from_xlsx(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'Döşeme']);
        DB::table('tbAraUrun')->insert(['No' => 7, 'AraUrunAdi' => 'Döşeme Kılıf', 'BolumAdiNo' => 2]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 10,
            'TamponMiktar' => 5,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('sonrakiUrunAdetleriniGuncelle')->once()->with('7', 10, 31);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('7');
        $this->app->instance(BomService::class, $bomService);

        $file = $this->createWorkbookUpload('stoklar.xlsx', [
            ['No', 'Bölüm', 'Ara Ürün', 'Ürün Çeşidi', 'Adet', 'Görevdeki', 'Boşta/Tampon'],
            [4, 'Döşeme', 'Döşeme Kılıf', 'Ara Mamül', 31, 14, 17],
            ['', 'Ürün Depo', 'Sanal Stok', 'Nihayi Ürün', 0, 0, 0],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->post('/api/stocks/import', ['file' => $file], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updated' => 1,
                'errors' => 0,
            ]);

        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 4,
            'Adet' => 31,
            'TamponMiktar' => 17,
        ]);
    }

    public function test_manual_stock_update_preserves_reserved_quantity_when_requested(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'YM Depo']);
        DB::table('tbAraUrun')->insert(['No' => 7, 'AraUrunAdi' => 'YM/ayak zade bohem(legna) 19cm', 'BolumAdiNo' => 2]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 600,
            'TamponMiktar' => 0,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('sonrakiUrunAdetleriniGuncelle')->once()->with('7', 600, 700);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('7');
        $this->app->instance(BomService::class, $bomService);

        $response = $this->actingAs($this->makeAdminUser())
            ->putJson('/api/stocks/4', [
                'Adet' => 700,
                'TamponMiktar' => 0,
                'preserve_reserved' => true,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 4,
            'Adet' => 700,
            'TamponMiktar' => 100,
        ]);
    }

    public function test_manual_stock_add_defaults_added_quantity_to_free_buffer(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'YM Depo']);
        DB::table('tbAraUrun')->insert(['No' => 7, 'AraUrunAdi' => 'YM/ayak zade bohem(legna) 19cm', 'BolumAdiNo' => 2]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 600,
            'TamponMiktar' => 0,
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('sonrakiUrunAdetleriniGuncelle')->once()->with('7', 600, 700);
        $bomService->shouldReceive('personelGorevTabloGuncelle')->once()->with('7');
        $this->app->instance(BomService::class, $bomService);

        $response = $this->actingAs($this->makeAdminUser())
            ->postJson('/api/stocks', [
                'component_id' => 7,
                'department_id' => 2,
                'quantity' => 100,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbBolumAraStok', [
            'No' => 4,
            'Adet' => 700,
            'TamponMiktar' => 100,
        ]);
    }

    public function test_stock_export_returns_editable_excel_workbook(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'Döşeme']);
        DB::table('tbAraUrun')->insert([
            'No' => 7,
            'AraUrunAdi' => 'Döşeme Kılıf',
            'BolumAdiNo' => 2,
            'UrunCesidi' => 'Ara Mamül',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 25,
            'TamponMiktar' => 12,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->get('/api/stocks/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->getContent();

        $this->assertStringStartsWith('PK', $content);

        $tmp = tempnam(sys_get_temp_dir(), 'stock_export_test_');
        file_put_contents($tmp, $content);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);

        $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $tableXml = $zip->getFromName('xl/tables/table1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertIsString($worksheetXml);
        $this->assertIsString($tableXml);
        $this->assertStringContainsString('Döşeme Kılıf', $worksheetXml);
        $this->assertStringContainsString('TableStyleMedium2', $tableXml);
    }

    public function test_stock_list_and_export_ignore_invalid_sort_parameters(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 2, 'BolumAdi' => 'Döşeme']);
        DB::table('tbAraUrun')->insert(['No' => 7, 'AraUrunAdi' => 'Döşeme Kılıf', 'BolumAdiNo' => 2]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 7,
            'Adet' => 25,
            'TamponMiktar' => 12,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?sort_by=bad&sort_dir=sideways')
            ->assertOk()
            ->assertJsonPath('data.0.No', 4);

        $this->actingAs($this->makeAdminUser())
            ->get('/api/stocks/export?sort_by=bad&sort_dir=sideways')
            ->assertOk();
    }

    public function test_stock_list_includes_inventory_without_stock_row_as_zero(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 1023, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 7,
                'AraUrunAdi' => 'Rock Beyaz',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
            [
                'No' => 8,
                'AraUrunAdi' => 'Rock Sütlü Kahve',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 1023,
            'AraUrunAdiNo' => 7,
            'Adet' => 3,
            'TamponMiktar' => 3,
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?' . http_build_query([
                'search' => 'Rock',
                'component_type' => 'Nihayi Ürün',
                'sort_by' => 'No',
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.No', 4)
            ->assertJsonPath('data.0.AraUrunAdiNo', 7)
            ->assertJsonPath('data.0.Adet', 3)
            ->assertJsonPath('data.1.No', null)
            ->assertJsonPath('data.1.AraUrunAdiNo', 8)
            ->assertJsonPath('data.1.Adet', 0)
            ->assertJsonPath('data.1.TamponMiktar', 0)
            ->assertJsonPath('data.1.BolumAdi', 'Ürün Depo');
    }

    public function test_missing_stock_rows_can_be_backfilled_with_unique_stock_numbers(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 1023, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 7,
                'AraUrunAdi' => 'Mevcut Stoklu Ürün',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
            [
                'No' => 8,
                'AraUrunAdi' => 'Eksik Stok Satırı 1',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Ara Mamül',
            ],
            [
                'No' => 9,
                'AraUrunAdi' => 'Eksik Stok Satırı 2',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Ham Madde',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 1023,
            'AraUrunAdiNo' => 7,
            'Adet' => 3,
            'TamponMiktar' => 3,
        ]);

        $this->artisan('stocks:backfill-missing-rows')
            ->expectsOutput('Dry-run: 2 missing stock rows found.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('tbBolumAraStok', ['AraUrunAdiNo' => 8]);

        $this->artisan('stocks:backfill-missing-rows', ['--apply' => true])
            ->expectsOutput('2 missing stock rows created with unique stock numbers.')
            ->assertExitCode(0);

        $createdRows = DB::table('tbBolumAraStok')
            ->whereIn('AraUrunAdiNo', [8, 9])
            ->orderBy('No')
            ->get();

        $this->assertCount(2, $createdRows);
        $this->assertSame([5, 6], $createdRows->pluck('No')->all());
        $this->assertSame([8, 9], $createdRows->pluck('AraUrunAdiNo')->all());
        $this->assertSame([0, 0], $createdRows->pluck('Adet')->all());
        $this->assertSame([0, 0], $createdRows->pluck('TamponMiktar')->all());
    }

    public function test_stock_list_exposes_special_production_available_quantity(): void
    {
        $this->createLegacyOrdersTable();
        $this->createLegacyProductsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 1023, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbUrunler')->insert([
            'No' => 7,
            'UrunID' => 'Puf Zem',
            'SistemAdi' => 'Puf Zem',
        ]);
        DB::table('tbAraUrun')->insert([
            'No' => 11,
            'AraUrunAdi' => 'Puf Zem',
            'BolumAdiNo' => 1023,
            'UrunCesidi' => 'Nihayi Ürün',
        ]);
        DB::table('tbBolumAraStok')->insert([
            'No' => 4,
            'BolumAdiNo' => 1023,
            'AraUrunAdiNo' => 11,
            'Adet' => 0,
            'TamponMiktar' => 0,
        ]);
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 10,
                'SiparisNo' => 'STOK-GIED-DECISION',
                'Musteri' => 'ÖZEL ÜRETİM DENEME',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 5,
                'Durum' => 'IsEmriVerildi',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => 101,
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 11,
                'SiparisNo' => 'S-GIED-RESERVED',
                'Musteri' => 'Normal Musteri',
                'UrunAdi' => 'Puf Zem',
                'Adet' => 2,
                'Durum' => 'UretimdenKarsilaniyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'GorevNo' => null,
                'BagliOlduguOzelUretimNo' => 10,
            ],
        ]);

        $response = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?' . http_build_query([
                'search' => 'Puf Zem',
                'sort_by' => 'No',
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.AraUrunAdiNo', 11)
            ->assertJsonPath('data.0.UretimToplamAdet', 5)
            ->assertJsonPath('data.0.UretimRezerveAdet', 2)
            ->assertJsonPath('data.0.UretimMusaitAdet', 3);
    }

    public function test_stock_list_filters_zero_and_nonzero_stock_totals(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();

        DB::table('tbBolum')->insert(['No' => 1023, 'BolumAdi' => 'Ürün Depo']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 7,
                'AraUrunAdi' => 'Sıfır Stoklu Ürün',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
            [
                'No' => 8,
                'AraUrunAdi' => 'Dolu Stoklu Ürün',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
            [
                'No' => 9,
                'AraUrunAdi' => 'Stok Satırı Yok',
                'BolumAdiNo' => 1023,
                'UrunCesidi' => 'Nihayi Ürün',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            [
                'No' => 4,
                'BolumAdiNo' => 1023,
                'AraUrunAdiNo' => 7,
                'Adet' => 0,
                'TamponMiktar' => 0,
            ],
            [
                'No' => 5,
                'BolumAdiNo' => 1023,
                'AraUrunAdiNo' => 8,
                'Adet' => 12,
                'TamponMiktar' => 12,
            ],
        ]);

        $zeroResponse = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?' . http_build_query([
                'stock_status' => 'zero',
                'sort_by' => 'No',
            ]));

        $zeroResponse->assertOk()->assertJsonPath('total', 2);
        $this->assertSame([7, 9], collect($zeroResponse->json('data'))->pluck('AraUrunAdiNo')->all());

        $nonzeroResponse = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?' . http_build_query([
                'stock_status' => 'nonzero',
                'sort_by' => 'No',
            ]));

        $nonzeroResponse
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.AraUrunAdiNo', 8)
            ->assertJsonPath('data.0.Adet', 12);
    }

    public function test_personnel_api_preserves_legacy_admin_department_zero(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();
        $this->createLegacyPersonnelTasksTable();

        DB::table('tbBolum')->insert([
            ['No' => 0, 'BolumAdi' => 'Yönetici'],
            ['No' => 1023, 'BolumAdi' => 'Ürün Depo'],
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 27,
            'Ad' => 'Mustafa Ali Acar',
            'Soyad' => '',
            'Mail' => 'mustafaalia@gmail.com',
            'BolumAdiNo' => 0,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->getJson('/api/database/personnel?search=mustafa')
            ->assertOk()
            ->assertJsonPath('data.0.department_id', 0)
            ->assertJsonPath('data.0.department_name', 'Yönetici');
    }

    public function test_personnel_update_rejects_blank_department_without_promoting_to_admin(): void
    {
        $this->createLegacyDepartmentsTable();
        $this->createLegacyPersonnelTable();

        DB::table('tbBolum')->insert([
            ['No' => 0, 'BolumAdi' => 'Yönetici'],
            ['No' => 1023, 'BolumAdi' => 'Ürün Depo'],
        ]);

        DB::table('tbPersonel')->insert([
            'PersonelNo' => 27,
            'Ad' => 'Mustafa Ali Acar',
            'Soyad' => '',
            'Adres' => '102',
            'Telefon' => '12',
            'Mail' => 'mustafaalia@gmail.com',
            'Sifre' => hash('sha256', '123'),
            'BolumAdiNo' => 1023,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->putJson('/api/database/personnel/27', [
                'name' => 'Mustafa Ali Acar',
                'surname' => '',
                'address' => '102',
                'phone' => '12',
                'email' => 'mustafaalia@gmail.com',
                'department_id' => '',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('tbPersonel', [
            'PersonelNo' => 27,
            'BolumAdiNo' => 1023,
        ]);
    }

    private function createWorkbookUpload(string $fileName, array $rows): UploadedFile
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for workbook import/export tests.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'stock_import_test_');
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp, \ZipArchive::OVERWRITE) === true);

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Stoklar" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildWorksheetXml($rows));
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        $this->assertIsString($content);

        return UploadedFile::fake()->createWithContent($fileName, $content);
    }

    private function buildWorksheetXml(array $rows): string
    {
        $sheetData = '';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = $this->excelColumnName($columnIndex + 1) . $rowNumber;
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="' . $cellRef . '"><v>' . $value . '</v></c>';
                    continue;
                }

                $cells .= '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">'
                    . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    . '</t></is></c>';
            }

            $sheetData .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name ?: 'A';
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
