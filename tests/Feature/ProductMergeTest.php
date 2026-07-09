<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class ProductMergeTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
    }

    public function test_component_merge_preview_is_read_only_and_apply_moves_references_stock_and_bom_paths(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createCriticalStockTables();
        $this->createLegacyIssuedTasksTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->addProductMergeTestSchema();

        DB::table('tbAraUrun')->insert([
            ['No' => 10, 'AraUrunAdi' => 'Eski parca', 'UrunCesidi' => 'Ara Mamül', 'BolumAdiNo' => 4, 'Yol' => ''],
            ['No' => 20, 'AraUrunAdi' => 'Dogru parca', 'UrunCesidi' => 'Ara Mamül', 'BolumAdiNo' => 4, 'Yol' => ''],
            ['No' => 30, 'AraUrunAdi' => 'Ust parca', 'UrunCesidi' => 'Ara Mamül', 'BolumAdiNo' => 4, 'Yol' => '10-2:101-1'],
        ]);
        DB::table('tbUrunler')->insert([
            'No' => 500,
            'UrunID' => 'Final urun',
            'AraAdlarYol' => '10-30-1:30-10-2:101-30-1',
        ]);
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-1',
                'UrunAdi' => 'Eski parca',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 10,
                'EslesenUrunTur' => 'Ara Mamül',
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-2',
                'UrunAdi' => 'Final ayni numara',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 10,
                'EslesenUrunTur' => 'Nihai',
            ],
        ]);
        DB::table('tbUrunEslestirmeOnbellek')->insert([
            'ExcelUrunAdi' => 'Eski parca excel',
            'EslesenUrunNo' => 10,
            'EslesenUrunTur' => 'Ara Mamül',
        ]);
        DB::table('tbBolumHavuz')->insert(['UrunIDNo' => 500, 'AraUrunAdiNo' => 10, 'BolumAdiNo' => 4, 'Adet' => 3, 'ToplamAdet' => 3]);
        DB::table('tbPersonelGorev')->insert(['UrunIDNo' => 500, 'PersonelNo' => 22, 'AraUrunAdiNo' => 10, 'BolumAdiNo' => 4, 'Adet' => 3, 'BekleyenAdet' => 0]);
        DB::table('tbGorevler')->insert(['No' => 11, 'UrunIDNo' => 500, 'AraUrunAdiNo' => 10, 'BolumAdiNo' => 4, 'ToplamAdet' => 3]);
        DB::table('tbBolumAraStok')->insert([
            ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 10, 'Adet' => 5, 'TamponMiktar' => 2],
            ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 20, 'Adet' => 3, 'TamponMiktar' => 1],
            ['BolumAdiNo' => 5, 'AraUrunAdiNo' => 10, 'Adet' => 7, 'TamponMiktar' => 7],
        ]);
        DB::table('tbKritikStokEsik')->insert(['AraUrunAdiNo' => 10, 'EsikMiktar' => 5, 'Aktif' => 1]);
        DB::table('tbKritikStokUyari')->insert(['AraUrunAdiNo' => 10, 'EsikMiktar' => 5, 'MevcutStok' => 1]);
        DB::table('tbVerilenGorevler')->insert(['UrunIDNo' => 10, 'ToplamAdet' => 3]);
        DB::table('tbIsEmriGecmisi')->insert([
            'SiparisSatirNo' => 1,
            'IslemTipi' => 'Olustu',
            'IslemTarihi' => now(),
            'EslesenUrunNo' => 10,
            'EslesenUrunTur' => 'Ara Mamül',
        ]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/merge-preview', [
                'merge_type' => 'component',
                'source_id' => 10,
                'target_id' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview.merge_type', 'component')
            ->assertJsonPath('preview.total_count', 12);

        $this->assertSame('10-2:101-1', DB::table('tbAraUrun')->where('No', 30)->value('Yol'));
        $this->assertSame('10-30-1:30-10-2:101-30-1', DB::table('tbUrunler')->where('No', 500)->value('AraAdlarYol'));

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/merge', [
                'merge_type' => 'component',
                'source_id' => 10,
                'target_id' => 20,
                'confirm' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tbAraUrun', ['No' => 10, 'MergedIntoNo' => 20]);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 1, 'EslesenUrunNo' => 20, 'EslesenUrunTur' => 'Ara Mamül']);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 2, 'EslesenUrunNo' => 10, 'EslesenUrunTur' => 'Nihai']);
        $this->assertDatabaseHas('tbBolumHavuz', ['AraUrunAdiNo' => 20]);
        $this->assertDatabaseHas('tbPersonelGorev', ['AraUrunAdiNo' => 20]);
        $this->assertDatabaseHas('tbGorevler', ['AraUrunAdiNo' => 20]);
        $this->assertDatabaseHas('tbKritikStokEsik', ['AraUrunAdiNo' => 20]);
        $this->assertDatabaseHas('tbKritikStokUyari', ['AraUrunAdiNo' => 20]);
        $this->assertDatabaseHas('tbVerilenGorevler', ['UrunIDNo' => 20]);
        $this->assertSame('20-2:101-1', DB::table('tbAraUrun')->where('No', 30)->value('Yol'));
        $this->assertSame('20-30-1:30-20-2:101-30-1', DB::table('tbUrunler')->where('No', 500)->value('AraAdlarYol'));
        $this->assertDatabaseHas('tbBolumAraStok', ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 20, 'Adet' => 8, 'TamponMiktar' => 3]);
        $this->assertDatabaseHas('tbBolumAraStok', ['BolumAdiNo' => 5, 'AraUrunAdiNo' => 20, 'Adet' => 7, 'TamponMiktar' => 7]);
        $this->assertSame(0, DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 10)->count());
        $this->assertDatabaseHas('product_merge_logs', ['merge_type' => 'component', 'source_no' => 10, 'target_no' => 20]);
    }

    public function test_final_product_merge_moves_final_references_and_hides_source_from_product_list(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createCriticalStockTables();
        $this->createLegacyIssuedTasksTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->addProductMergeTestSchema();

        DB::table('tbUrunler')->insert([
            ['No' => 100, 'UrunID' => 'Eski final', 'SistemAdi' => 'Eski final', 'AraAdlarYol' => ''],
            ['No' => 200, 'UrunID' => 'Dogru final', 'SistemAdi' => 'Dogru final', 'AraAdlarYol' => ''],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 1000, 'AraUrunAdi' => 'Eski final', 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => ''],
            ['No' => 2000, 'AraUrunAdi' => 'Dogru final', 'UrunCesidi' => 'Nihayi Ürün', 'Yol' => ''],
        ]);
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-1',
                'UrunAdi' => 'Eski final',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 100,
                'EslesenUrunTur' => 'Nihai',
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-2',
                'UrunAdi' => 'Ara ayni numara',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 100,
                'EslesenUrunTur' => 'Ara Mamül',
            ],
        ]);
        DB::table('tbUrunEslestirmeOnbellek')->insert(['ExcelUrunAdi' => 'Eski final excel', 'EslesenUrunNo' => 100, 'EslesenUrunTur' => 'Nihai']);
        DB::table('tbBolumHavuz')->insert(['UrunIDNo' => 100, 'AraUrunAdiNo' => 1000, 'BolumAdiNo' => 4, 'Adet' => 2, 'ToplamAdet' => 2]);
        DB::table('tbPersonelGorev')->insert(['UrunIDNo' => 100, 'PersonelNo' => 22, 'AraUrunAdiNo' => 1000, 'BolumAdiNo' => 4, 'Adet' => 2, 'BekleyenAdet' => 0]);
        DB::table('tbGorevler')->insert(['No' => 1, 'UrunIDNo' => 100, 'AraUrunAdiNo' => 1000, 'ToplamAdet' => 2]);
        DB::table('tbBolumAraStok')->insert(['BolumAdiNo' => 4, 'AraUrunAdiNo' => 1000, 'UrunIDNo' => 100, 'Adet' => 2, 'TamponMiktar' => 1]);
        DB::table('tbKritikStokEsik')->insert(['AraUrunAdiNo' => 1000, 'UrunIDNo' => 100, 'EsikMiktar' => 5, 'Aktif' => 1]);
        DB::table('tbVerilenGorevler')->insert(['UrunIDNo' => 100, 'ToplamAdet' => 2]);
        DB::table('tbSetIcerikleri')->insert(['SetNo' => 1, 'UrunNo' => 100, 'Adet' => 1]);
        DB::table('tbIsEmriGecmisi')->insert([
            'SiparisSatirNo' => 1,
            'IslemTipi' => 'Olustu',
            'IslemTarihi' => now(),
            'EslesenUrunNo' => 100,
            'EslesenUrunTur' => 'Nihai',
        ]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/merge', [
                'merge_type' => 'product',
                'source_id' => 100,
                'target_id' => 200,
                'confirm' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tbUrunler', ['No' => 100, 'MergedIntoNo' => 200]);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 1, 'EslesenUrunNo' => 200, 'EslesenUrunTur' => 'Nihai']);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 2, 'EslesenUrunNo' => 100, 'EslesenUrunTur' => 'Ara Mamül']);
        $this->assertDatabaseHas('tbUrunEslestirmeOnbellek', ['ExcelUrunAdi' => 'Eski final excel', 'EslesenUrunNo' => 200, 'EslesenUrunTur' => 'Nihai']);
        $this->assertDatabaseHas('tbBolumHavuz', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbPersonelGorev', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbGorevler', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbBolumAraStok', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbKritikStokEsik', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbVerilenGorevler', ['UrunIDNo' => 200]);
        $this->assertDatabaseHas('tbSetIcerikleri', ['UrunNo' => 200]);

        $products = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/database/products')
            ->assertOk()
            ->json('data');

        $this->assertSame([200], collect($products)->pluck('id')->sort()->values()->all());
        $this->assertDatabaseHas('product_merge_logs', ['merge_type' => 'product', 'source_no' => 100, 'target_no' => 200]);
    }

    public function test_final_product_merge_can_include_linked_root_component_stock_in_one_step(): void
    {
        $this->createLegacyProductsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();
        $this->createLegacyPersonnelTasksTable();
        $this->createLegacyWorkOrdersTable();
        $this->createLegacyOrdersTable();
        $this->createLegacyOrderMatchingTables();
        $this->createCriticalStockTables();
        $this->createLegacyIssuedTasksTable();
        $this->createLegacyWorkOrderHistoryTable();
        $this->addProductMergeTestSchema();

        DB::table('tbBolum')->insert(['No' => 4, 'BolumAdi' => 'Dikim']);
        DB::table('tbUrunler')->insert([
            ['No' => 100, 'UrunID' => 'Eski final', 'SistemAdi' => 'Eski final', 'AraAdlarYol' => '1000-3'],
            ['No' => 200, 'UrunID' => 'Dogru final', 'SistemAdi' => 'Dogru final', 'AraAdlarYol' => '2000-3'],
        ]);
        DB::table('tbAraUrun')->insert([
            ['No' => 1000, 'AraUrunAdi' => 'Eski final', 'UrunCesidi' => 'Nihayi Ürün', 'BolumAdiNo' => 4, 'Yol' => ''],
            ['No' => 2000, 'AraUrunAdi' => 'Dogru final', 'UrunCesidi' => 'Nihayi Ürün', 'BolumAdiNo' => 4, 'Yol' => ''],
        ]);
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-1',
                'UrunAdi' => 'Eski final',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 100,
                'EslesenUrunTur' => 'Nihai',
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-2',
                'UrunAdi' => 'Eski final kok',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 1000,
                'EslesenUrunTur' => 'Ara Mamül',
            ],
        ]);
        DB::table('tbBolumHavuz')->insert(['UrunIDNo' => 100, 'AraUrunAdiNo' => 1000, 'BolumAdiNo' => 4, 'Adet' => 2, 'ToplamAdet' => 2]);
        DB::table('tbPersonelGorev')->insert(['UrunIDNo' => 100, 'PersonelNo' => 22, 'AraUrunAdiNo' => 1000, 'BolumAdiNo' => 4, 'Adet' => 2, 'BekleyenAdet' => 0]);
        DB::table('tbGorevler')->insert(['No' => 1, 'UrunIDNo' => 100, 'AraUrunAdiNo' => 1000, 'ToplamAdet' => 2]);
        DB::table('tbBolumAraStok')->insert([
            ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 1000, 'UrunIDNo' => 100, 'Adet' => 5, 'TamponMiktar' => 3],
            ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 2000, 'UrunIDNo' => 200, 'Adet' => 7, 'TamponMiktar' => 4],
        ]);
        DB::table('tbKritikStokEsik')->insert(['AraUrunAdiNo' => 1000, 'UrunIDNo' => 100, 'EsikMiktar' => 5, 'Aktif' => 1]);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/merge-preview', [
                'merge_type' => 'product',
                'source_id' => 100,
                'target_id' => 200,
                'include_linked_component' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview.include_linked_component', true)
            ->assertJsonPath('preview.linked_component.source.id', 1000)
            ->assertJsonPath('preview.linked_component.target.id', 2000);

        $this->actingAs($this->makeAdminUser())
            ->postJson('/api/database/products/merge', [
                'merge_type' => 'product',
                'source_id' => 100,
                'target_id' => 200,
                'include_linked_component' => true,
                'confirm' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tbUrunler', ['No' => 100, 'MergedIntoNo' => 200]);
        $this->assertDatabaseHas('tbAraUrun', ['No' => 1000, 'MergedIntoNo' => 2000]);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 1, 'EslesenUrunNo' => 200, 'EslesenUrunTur' => 'Nihai']);
        $this->assertDatabaseHas('tbSiparisSatir', ['No' => 2, 'EslesenUrunNo' => 2000, 'EslesenUrunTur' => 'Nihayi Ürün']);
        $this->assertDatabaseHas('tbBolumHavuz', ['UrunIDNo' => 200, 'AraUrunAdiNo' => 2000]);
        $this->assertDatabaseHas('tbPersonelGorev', ['UrunIDNo' => 200, 'AraUrunAdiNo' => 2000]);
        $this->assertDatabaseHas('tbGorevler', ['UrunIDNo' => 200, 'AraUrunAdiNo' => 2000]);
        $this->assertDatabaseHas('tbBolumAraStok', ['BolumAdiNo' => 4, 'AraUrunAdiNo' => 2000, 'Adet' => 12, 'TamponMiktar' => 7]);
        $this->assertSame(0, DB::table('tbBolumAraStok')->where('AraUrunAdiNo', 1000)->count());
        $this->assertDatabaseHas('tbKritikStokEsik', ['AraUrunAdiNo' => 2000, 'UrunIDNo' => 200]);

        $stocks = $this->actingAs($this->makeAdminUser())
            ->getJson('/api/stocks?per_page=1000')
            ->assertOk()
            ->json('data');

        $this->assertFalse(collect($stocks)->pluck('AraUrunAdiNo')->contains(1000));
        $this->assertTrue(collect($stocks)->pluck('AraUrunAdiNo')->contains(2000));
        $this->assertDatabaseHas('product_merge_logs', ['merge_type' => 'product', 'source_no' => 100, 'target_no' => 200]);
        $this->assertDatabaseHas('product_merge_logs', ['merge_type' => 'component', 'source_no' => 1000, 'target_no' => 2000]);
    }

    private function addProductMergeTestSchema(): void
    {
        foreach (['tbUrunler', 'tbAraUrun'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'MergedIntoNo')) {
                    $blueprint->integer('MergedIntoNo')->nullable();
                }
                if (!Schema::hasColumn($table, 'MergedAt')) {
                    $blueprint->dateTime('MergedAt')->nullable();
                }
                if (!Schema::hasColumn($table, 'MergedBy')) {
                    $blueprint->integer('MergedBy')->nullable();
                }
            });
        }

        Schema::create('product_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->string('merge_type', 30);
            $table->unsignedBigInteger('source_no');
            $table->string('source_name', 500)->nullable();
            $table->unsignedBigInteger('target_no');
            $table->string('target_name', 500)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name', 200)->nullable();
            $table->json('preview')->nullable();
            $table->json('applied_changes')->nullable();
            $table->timestamp('created_at')->nullable();
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
