<?php

namespace Tests\Unit;

use App\Services\WorkOrderBomPreviewService;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class WorkOrderBomPreviewServiceTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
        $this->createLegacyDepartmentsTable();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createLegacyPoolTable();

        DB::table('tbBolum')->insert(['No' => 1, 'BolumAdi' => 'Kesim']);
        DB::table('tbAraUrun')->insert([
            [
                'No' => 10,
                'AraUrunAdi' => 'Koltuk Ana Gövde',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Nihayi Ürün',
                'Yol' => '20-10-2:30-10-1',
            ],
            [
                'No' => 20,
                'AraUrunAdi' => 'Ahşap Ayak',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
            [
                'No' => 30,
                'AraUrunAdi' => 'Kumaş',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
        ]);

        DB::table('tbBolumAraStok')->insert([
            ['BolumAdiNo' => 1, 'Adet' => 0, 'AraUrunAdiNo' => 10, 'UrunIDNo' => 0, 'TamponMiktar' => 0],
            ['BolumAdiNo' => 1, 'Adet' => 5, 'AraUrunAdiNo' => 20, 'UrunIDNo' => 0, 'TamponMiktar' => 3],
            ['BolumAdiNo' => 1, 'Adet' => 1, 'AraUrunAdiNo' => 30, 'UrunIDNo' => 0, 'TamponMiktar' => 0],
        ]);
    }

    public function test_manual_preview_builds_bom_stock_metrics_and_predicted_tasks_without_writing_pool_rows(): void
    {
        $result = app(WorkOrderBomPreviewService::class)
            ->buildManualPreview(10, 'Nihai', 2, 'StokDahil');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['summary']['groupCount']);

        $group = $result['groups'][0];
        $this->assertSame(3, $group['summary']['taskCount']);
        $this->assertSame(5, $group['summary']['totalTaskQuantity']);
        $this->assertSame(3, $group['summary']['stockReserved']);

        $legNode = collect($group['tree']['nodes'])->firstWhere('no', 20);
        $this->assertSame(5, $legNode['stock']['warehouse']);
        $this->assertSame(2, $legNode['stock']['reserved']);
        $this->assertSame(3, $legNode['stock']['free']);
        $this->assertSame(4, $legNode['required']);

        $legTask = collect($group['tasks'])->firstWhere('componentNo', 20);
        $this->assertSame(4, $legTask['requestedQuantity']);
        $this->assertSame(1, $legTask['totalQuantity']);
        $this->assertSame(1, $legTask['assignableQuantity']);
        $this->assertSame(3, $legTask['reservedFromStock']);

        $this->assertSame(0, DB::table('tbBolumHavuz')->count());
    }

    public function test_direct_component_preview_uses_root_free_stock_when_stock_included(): void
    {
        DB::table('tbAraUrun')->insert([
            [
                'No' => 200,
                'AraUrunAdi' => 'TER/kanepe zem legna zeugma keten gri',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara Mamül',
                'Yol' => '201-1',
            ],
            [
                'No' => 201,
                'AraUrunAdi' => 'KES/kanepe zem legna zeugma keten gri',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 1,
            'Adet' => 31,
            'AraUrunAdiNo' => 200,
            'UrunIDNo' => 0,
            'TamponMiktar' => 16,
        ]);

        $result = app(WorkOrderBomPreviewService::class)
            ->buildManualPreview(200, 'Ara', 20, 'StokDahil');

        $this->assertTrue($result['success']);
        $group = $result['groups'][0];
        $this->assertSame(2, $group['summary']['taskCount']);
        $this->assertSame(8, $group['summary']['totalTaskQuantity']);
        $this->assertSame(16, $group['summary']['stockReserved']);

        $rootTask = collect($group['tasks'])->firstWhere('componentNo', 200);
        $childTask = collect($group['tasks'])->firstWhere('componentNo', 201);
        $rootNode = collect($group['tree']['nodes'])->firstWhere('no', 200);

        $this->assertSame(20, $rootTask['requestedQuantity']);
        $this->assertSame(4, $rootTask['totalQuantity']);
        $this->assertSame(16, $rootTask['reservedFromStock']);
        $this->assertSame(4, $childTask['totalQuantity']);
        $this->assertSame(16, $rootNode['stockReserved']);
        $this->assertSame(4, $rootNode['netRequired']);
    }

    public function test_manual_preview_does_not_skip_leaf_when_parent_is_only_theoretically_producible(): void
    {
        DB::table('tbAraUrun')->insert([
            [
                'No' => 100,
                'AraUrunAdi' => 'Berjer',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Nihayi Ürün',
                'Yol' => '101-1',
            ],
            [
                'No' => 101,
                'AraUrunAdi' => 'Terzi parçası',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara Mamül',
                'Yol' => '102-1',
            ],
            [
                'No' => 102,
                'AraUrunAdi' => 'Kesim parçası',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 1,
            'Adet' => 5,
            'AraUrunAdiNo' => 102,
            'UrunIDNo' => 0,
            'TamponMiktar' => 1,
        ]);

        $result = app(WorkOrderBomPreviewService::class)
            ->buildManualPreview(100, 'Nihai', 3, 'StokDahil');

        $this->assertTrue($result['success']);
        $group = $result['groups'][0];
        $this->assertSame(3, $group['summary']['taskCount']);
        $this->assertNotNull(collect($group['tasks'])->firstWhere('componentNo', 101));

        $leafTask = collect($group['tasks'])->firstWhere('componentNo', 102);
        $this->assertNotNull($leafTask);
        $this->assertSame(3, $leafTask['requestedQuantity']);
        $this->assertSame(2, $leafTask['totalQuantity']);
        $this->assertSame(1, $leafTask['reservedFromStock']);
    }

    public function test_manual_preview_stops_descending_when_parent_buffer_fully_covers_need(): void
    {
        DB::table('tbAraUrun')->insert([
            [
                'No' => 100,
                'AraUrunAdi' => 'Berjer',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Nihayi Ürün',
                'Yol' => '101-1',
            ],
            [
                'No' => 101,
                'AraUrunAdi' => 'Terzi parçası',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara Mamül',
                'Yol' => '102-1',
            ],
            [
                'No' => 102,
                'AraUrunAdi' => 'Kesim parçası',
                'Performans' => 0,
                'BolumAdiNo' => 1,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ham Madde',
                'Yol' => '',
            ],
        ]);
        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 1,
            'Adet' => 3,
            'AraUrunAdiNo' => 101,
            'UrunIDNo' => 0,
            'TamponMiktar' => 3,
        ]);

        $result = app(WorkOrderBomPreviewService::class)
            ->buildManualPreview(100, 'Nihai', 3, 'StokDahil');

        $this->assertTrue($result['success']);
        $group = $result['groups'][0];
        $this->assertSame(1, $group['summary']['taskCount']);
        $this->assertSame(3, $group['summary']['totalTaskQuantity']);
        $this->assertSame(3, $group['summary']['stockReserved']);
        $this->assertNull(collect($group['tasks'])->firstWhere('componentNo', 101));
        $this->assertNull(collect($group['tasks'])->firstWhere('componentNo', 102));
    }
}
