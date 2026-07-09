<?php

namespace Tests\Unit;

use App\Services\BomService;
use App\Services\OrderToWorkOrderService;
use App\Services\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\TestCase;
use Tests\Support\UsesLegacyInMemoryDatabase;

class OrderToWorkOrderServiceTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();

        Schema::create('tbSiparisSatir', function (Blueprint $table) {
            $table->integer('No')->primary();
            $table->string('SiparisNo', 50)->nullable();
            $table->string('UrunAdi', 300)->nullable();
            $table->integer('Adet')->default(0);
            $table->string('Durum', 30)->nullable();
            $table->boolean('Aktif')->default(true);
            $table->integer('EslesenUrunNo')->nullable();
            $table->string('EslesenUrunTur', 10)->nullable();
            $table->string('EslesmeYontemi', 20)->nullable();
            $table->dateTime('IsEmriTarihi')->nullable();
            $table->integer('GorevNo')->nullable();
            $table->text('TamponDusumleri')->nullable();
            $table->integer('BagliOlduguOzelUretimNo')->nullable();
            $table->string('Musteri', 200)->nullable();
            $table->string('Kategori', 100)->nullable();
            $table->dateTime('KargoSonTeslim')->nullable();
        });
    }

    public function test_it_skips_orders_that_are_not_eligible_for_new_work_orders(): void
    {
        DB::table('tbSiparisSatir')->insert([
            [
                'No' => 1,
                'SiparisNo' => 'S-3001',
                'UrunAdi' => 'Puf A',
                'Adet' => 1,
                'Durum' => 'PasifDevamEden',
                'Aktif' => 1,
                'EslesenUrunNo' => 7,
                'EslesenUrunTur' => 'Nihai',
                'BagliOlduguOzelUretimNo' => null,
            ],
            [
                'No' => 2,
                'SiparisNo' => 'S-3002',
                'UrunAdi' => 'Puf B',
                'Adet' => 1,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => 8,
                'EslesenUrunTur' => 'Ara',
                'BagliOlduguOzelUretimNo' => 55,
            ],
        ]);

        $bomService = Mockery::mock(BomService::class);
        $workOrderService = Mockery::mock(WorkOrderService::class);
        $workOrderService->shouldNotReceive('createWorkOrderForProduct');
        $workOrderService->shouldNotReceive('createWorkOrderForComponent');

        $service = new OrderToWorkOrderService($bomService, $workOrderService);
        $result = $service->createOrderWorkOrders([1, 2]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(2, $result['skipped']);
        $this->assertStringContainsString('PasifDevamEden', $result['errors'][0]);
        $this->assertStringContainsString('GİED', $result['errors'][1]);
    }

    public function test_it_uses_cached_product_match_when_order_row_has_not_been_persisted_yet(): void
    {
        Schema::create('tbUrunEslestirmeOnbellek', function (Blueprint $table) {
            $table->increments('No');
            $table->string('ExcelUrunAdi', 300)->unique();
            $table->integer('EslesenUrunNo');
            $table->string('EslesenUrunTur', 10);
            $table->dateTime('OlusturmaTarihi')->nullable();
        });

        DB::table('tbSiparisSatir')->insert([
            'No' => 3,
            'SiparisNo' => 'S-3003',
            'UrunAdi' => 'Puf C',
            'Adet' => 2,
            'Durum' => 'UretimBekliyor',
            'Aktif' => 1,
            'EslesenUrunNo' => null,
            'EslesenUrunTur' => null,
            'BagliOlduguOzelUretimNo' => null,
            'Musteri' => 'Normal Musteri',
            'Kategori' => 'Puf',
        ]);

        DB::table('tbUrunEslestirmeOnbellek')->insert([
            'ExcelUrunAdi' => 'Puf C',
            'EslesenUrunNo' => 77,
            'EslesenUrunTur' => 'Nihai',
        ]);

        $bomService = Mockery::mock(BomService::class);
        $bomService->shouldReceive('logIsEmriGecmisi')->once();

        $workOrderService = Mockery::mock(WorkOrderService::class);
        $workOrderService
            ->shouldReceive('createWorkOrderForProduct')
            ->once()
            ->with(77, 2, 'StokDahil', '', Mockery::on(function ($traceContext) {
                return intval($traceContext['siparisSatirNo'] ?? 0) === 3
                    && ($traceContext['siparisNo'] ?? '') === 'S-3003';
            }))
            ->andReturn([
                'success' => true,
                'gorevNo' => 501,
                'tamponDusumleri' => [],
                'sistemUrunAdi' => 'Sistem Puf',
            ]);

        $service = new OrderToWorkOrderService($bomService, $workOrderService);
        $result = $service->createOrderWorkOrders([3]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('tbSiparisSatir', [
            'No' => 3,
            'EslesenUrunNo' => 77,
            'EslesenUrunTur' => 'Nihai',
            'EslesmeYontemi' => 'Onbellek',
            'Durum' => 'IsEmriVerildi',
            'GorevNo' => 501,
        ]);
    }
}
