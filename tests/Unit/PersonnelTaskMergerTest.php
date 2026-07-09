<?php

namespace Tests\Unit;

use App\Services\PersonnelTaskMerger;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesLegacyInMemoryDatabase;
use Tests\TestCase;

class PersonnelTaskMergerTest extends TestCase
{
    use UsesLegacyInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyInMemoryDatabase();
        $this->createLegacyComponentsTable();
        $this->createLegacyPersonnelTasksTable();
    }

    public function test_it_merges_same_visible_component_even_when_component_numbers_differ(): void
    {
        DB::table('tbAraUrun')->insert([
            [
                'No' => 44,
                'AraUrunAdi' => 'MAR/modica bench 18mm sunta',
                'Performans' => 0,
                'BolumAdiNo' => 3,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '',
            ],
            [
                'No' => 45,
                'AraUrunAdi' => 'MAR/modica bench 18mm sunta',
                'Performans' => 0,
                'BolumAdiNo' => 3,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '',
            ],
            [
                'No' => 46,
                'AraUrunAdi' => 'MAR/modica bench kasa',
                'Performans' => 0,
                'BolumAdiNo' => 3,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '',
            ],
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 1,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 09:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 44,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => null,
                'SiparisNo' => null,
            ],
            [
                'No' => 2,
                'UrunIDNo' => 11,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 11:30',
                'Adet' => 4,
                'BekleyenAdet' => 1,
                'Onay' => 'false',
                'AraUrunAdiNo' => 45,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => null,
                'SiparisNo' => null,
            ],
            [
                'No' => 3,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 12:00',
                'Adet' => 2,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 46,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 700,
                'SiparisNo' => 'SP-700',
            ],
            [
                'No' => 4,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 12:30',
                'Adet' => 3,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 46,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => 701,
                'SiparisNo' => 'SP-701',
            ],
        ]);

        app(PersonnelTaskMerger::class)->mergeOpenDuplicatesForPersonnel(25);

        $merged = DB::table('tbPersonelGorev')->where('No', 1)->first();

        $this->assertSame(13, intval($merged->Adet ?? 0));
        $this->assertSame(0, intval($merged->BekleyenAdet ?? 0));
        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 2]);
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 3, 'Adet' => 2]);
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 4, 'Adet' => 3]);
    }

    public function test_it_keeps_order_no_separate_when_order_item_no_is_missing(): void
    {
        DB::table('tbAraUrun')->insert([
            [
                'No' => 50,
                'AraUrunAdi' => 'MAR/legna kanepe kol',
                'Performans' => 0,
                'BolumAdiNo' => 4,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '',
            ],
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 20,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 09:00',
                'Adet' => 2,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 50,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => null,
                'SiparisNo' => 'STOK-A',
            ],
            [
                'No' => 21,
                'UrunIDNo' => 11,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 10:00',
                'Adet' => 3,
                'BekleyenAdet' => 0,
                'Onay' => 'hazir',
                'AraUrunAdiNo' => 50,
                'BolumAdiNo' => 4,
                'SiparisSatirNo' => null,
                'SiparisNo' => 'STOK-B',
            ],
        ]);

        app(PersonnelTaskMerger::class)->mergeOpenDuplicatesForPersonnel(25);

        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 20, 'Adet' => 2, 'SiparisNo' => 'STOK-A']);
        $this->assertDatabaseHas('tbPersonelGorev', ['No' => 21, 'Adet' => 3, 'SiparisNo' => 'STOK-B']);
    }

    public function test_it_recalculates_ready_and_waiting_amounts_when_merging_duplicates(): void
    {
        $this->createLegacyStocksTable();

        DB::table('tbAraUrun')->insert([
            [
                'No' => 70,
                'AraUrunAdi' => 'MAR/modica bench 18mm sunta',
                'Performans' => 0,
                'BolumAdiNo' => 3,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '71-70-1',
            ],
            [
                'No' => 71,
                'AraUrunAdi' => 'KES/modica bench 18mm sunta',
                'Performans' => 0,
                'BolumAdiNo' => 2,
                'MinAdet' => 0,
                'UrunCesidi' => 'Ara',
                'Yol' => '',
            ],
        ]);

        DB::table('tbBolumAraStok')->insert([
            'BolumAdiNo' => 2,
            'AraUrunAdiNo' => 71,
            'UrunIDNo' => 10,
            'Adet' => 8,
            'TamponMiktar' => 0,
        ]);

        DB::table('tbPersonelGorev')->insert([
            [
                'No' => 10,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 09:00',
                'Adet' => 8,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 70,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => null,
                'SiparisNo' => null,
            ],
            [
                'No' => 11,
                'UrunIDNo' => 10,
                'PersonelNo' => 25,
                'GorevBaslamaTarihi' => '14/05/2026 11:00',
                'Adet' => 4,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => 70,
                'BolumAdiNo' => 3,
                'SiparisSatirNo' => null,
                'SiparisNo' => null,
            ],
        ]);

        app(PersonnelTaskMerger::class)->mergeOpenDuplicatesForPersonnel(25);

        $merged = DB::table('tbPersonelGorev')->where('No', 10)->first();

        $this->assertSame(8, intval($merged->Adet ?? 0));
        $this->assertSame(4, intval($merged->BekleyenAdet ?? 0));
        $this->assertDatabaseMissing('tbPersonelGorev', ['No' => 11]);
    }
}
