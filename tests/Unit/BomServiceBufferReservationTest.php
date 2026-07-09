<?php

namespace Tests\Unit;

use App\Services\BomService;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class BomServiceBufferReservationTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
        $this->createLegacyComponentsTable();
        $this->createLegacyStocksTable();
        $this->createStockMovementsTable();
    }

    public function test_buffer_reservation_uses_available_stock_row_when_first_row_is_empty(): void
    {
        DB::table('tbAraUrun')->insert([
            'No' => 1411,
            'AraUrunAdi' => 'MAR/ayak ahsap torna 25cm tosca',
            'BolumAdiNo' => 4,
            'UrunCesidi' => 'Ham Madde',
        ]);

        DB::table('tbBolumAraStok')->insert([
            [
                'No' => 11328,
                'BolumAdiNo' => 22,
                'AraUrunAdiNo' => 1411,
                'Adet' => 0,
                'TamponMiktar' => 0,
            ],
            [
                'No' => 14066,
                'BolumAdiNo' => 4,
                'AraUrunAdiNo' => 1411,
                'Adet' => 150,
                'TamponMiktar' => 150,
            ],
        ]);

        $service = app(BomService::class);
        $reservations = $service->araStokTamponAzaltDetayli('1411', 11, [
            'siparisSatirNo' => 97,
            'siparisNo' => 'STOK-20260704-3045',
        ]);

        $this->assertSame([
            [
                'araNo' => 1411,
                'adet' => 11,
                'stokNo' => 14066,
                'bolumNo' => 4,
            ],
        ], $reservations);
        $this->assertSame(0, intval(DB::table('tbBolumAraStok')->where('No', 11328)->value('TamponMiktar')));
        $this->assertSame(139, intval(DB::table('tbBolumAraStok')->where('No', 14066)->value('TamponMiktar')));

        $service->restoreTamponFromJson(json_encode($reservations));

        $this->assertSame(150, intval(DB::table('tbBolumAraStok')->where('No', 14066)->value('TamponMiktar')));
    }
}
