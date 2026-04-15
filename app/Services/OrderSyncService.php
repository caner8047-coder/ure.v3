<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Sipariş Senkronizasyon Servisi
 * Legacy tablolar kullanır: tbSiparisSatir, tbUrunler, tbAraUrun, tbUrunEslestirmeOnbellek, tbSetTanimlari, tbSetIcerikleri
 */
class OrderSyncService
{
    public function getOrders($filters)
    {
        $query = DB::table('tbSiparisSatir');

        if (!isset($filters['show_all'])) {
            $query->where('Aktif', 1);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('SiparisNo', 'LIKE', "%$s%")
                    ->orWhere('Musteri', 'LIKE', "%$s%")
                    ->orWhere('UrunAdi', 'LIKE', "%$s%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('Durum', $filters['status']);
        }

        $total = $query->count();

        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 50;

        $orders = $query->orderByDesc('SiparisTarihi')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['success' => true, 'data' => $orders, 'totalCount' => $total];
    }

    public function uploadOrders(array $rows)
    {
        $inserted = 0;
        $updated = 0;
        $matched = 0;
        $unmatched = 0;
        $passivated = 0;

        $dbProducts = DB::table('tbUrunler')->get();
        $matchCache = DB::table('tbUrunEslestirmeOnbellek')->get()->keyBy(function ($item) {
            return strtolower(trim($item->ExcelUrunAdi));
        });
        $sets = DB::table('tbSetTanimlari')->where('Aktif', 1)->get()->keyBy(function ($set) {
            return strtolower(trim($set->ExcelSetAdi));
        });

        $touchedIds = [];
        $pendingWorkOrdersAlert = [];
        $newStockCodes = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $siparisNo = $row['siparisNo'] ?? null;
                $urunAdi = $row['urunAdi'] ?? null;
                if (!$siparisNo || !$urunAdi) continue;

                $musteriNotu = $row['musteriNotu'] ?? null;
                $pazaryeri = $row['pazaryeri'] ?? null;
                $magaza = $row['magaza'] ?? null;
                $musteri = $row['musteri'] ?? null;
                $adet = (int)($row['adet'] ?? 1);
                $kategori = $row['kategori'] ?? null;
                $stokKodu = $row['stokKodu'] ?? null;
                $siparisTarihi = $this->parseDate($row['siparisTarihi'] ?? null);
                $kargoSonTeslim = $this->parseDate($row['kargoSonTeslim'] ?? null);

                $existing = DB::table('tbSiparisSatir')
                    ->where('SiparisNo', $siparisNo)
                    ->where('UrunAdi', $urunAdi)
                    ->where(function ($q) use ($musteriNotu) {
                        if ($musteriNotu) $q->where('MusteriNotu', $musteriNotu);
                        else $q->whereNull('MusteriNotu')->orWhere('MusteriNotu', '');
                    })->first();

                $eslesenUrunNo = null;
                $eslesenUrunTur = null;
                $eslesmePuani = null;
                $eslesmeYontemi = null;
                $urunAdiLower = strtolower(trim($urunAdi));

                // StokKodu ile eşleştirme
                if ($stokKodu) {
                    $matchedProd = $dbProducts->firstWhere('SistemKodu', $stokKodu);
                    if ($matchedProd) {
                        $eslesenUrunNo = $matchedProd->No;
                        $eslesenUrunTur = 'Nihai';
                        $eslesmePuani = 100;
                        $eslesmeYontemi = 'StokKodu';
                    }
                }

                // Önbellek ile eşleştirme
                if (!$eslesenUrunNo && isset($matchCache[$urunAdiLower])) {
                    $cached = $matchCache[$urunAdiLower];
                    $eslesenUrunNo = $cached->EslesenUrunNo;
                    $eslesenUrunTur = $cached->EslesenUrunTur;
                    $eslesmePuani = 100;
                    $eslesmeYontemi = 'Onbellek';
                }

                // Tam isim eşleştirme
                if (!$eslesenUrunNo) {
                    $exact = $dbProducts->first(function ($p) use ($urunAdiLower) {
                        return strtolower(trim($p->SistemAdi ?? '')) === $urunAdiLower
                            || strtolower(trim($p->UrunID ?? '')) === $urunAdiLower;
                    });
                    if ($exact) {
                        $eslesenUrunNo = $exact->No;
                        $eslesenUrunTur = 'Nihai';
                        $eslesmePuani = 100;
                        $eslesmeYontemi = 'TamIsim';
                    }
                }

                if ($eslesenUrunNo || isset($sets[$urunAdiLower])) $matched++;
                else $unmatched++;

                if ($existing) {
                    $updateData = [
                        'Pazaryeri' => $pazaryeri,
                        'Magaza' => $magaza,
                        'SiparisTarihi' => $siparisTarihi,
                        'Musteri' => $musteri,
                        'Adet' => $adet,
                        'KargoSonTeslim' => $kargoSonTeslim,
                        'Kategori' => $kategori,
                        'StokKodu' => $stokKodu,
                        'Aktif' => 1,
                        'GuncellemeTarihi' => now(),
                    ];

                    if (($existing->EslesmeYontemi ?? '') !== 'Manuel') {
                        $updateData['EslesenUrunNo'] = $eslesenUrunNo;
                        $updateData['EslesenUrunTur'] = $eslesenUrunTur;
                        $updateData['EslesmePuani'] = $eslesmePuani;
                        $updateData['EslesmeYontemi'] = $eslesmeYontemi;
                    }

                    if ($existing->Durum !== 'IsEmriVerildi') {
                        $updateData['Durum'] = 'UretimBekliyor';
                    }

                    DB::table('tbSiparisSatir')->where('No', $existing->No)->update($updateData);
                    $touchedIds[] = $existing->No;
                    $updated++;

                    // Set Logic
                    if ($existing->Durum !== 'IsEmriVerildi' && isset($sets[$urunAdiLower])) {
                        $setDef = $sets[$urunAdiLower];
                        if (!$existing->SetMi) {
                            DB::table('tbSiparisSatir')->where('No', $existing->No)->update([
                                'SetMi' => 1, 'SetNo' => $setDef->No,
                                'EslesenUrunNo' => null, 'EslesenUrunTur' => null, 'EslesmeYontemi' => 'Set'
                            ]);
                            DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $existing->No)->delete();
                            $this->createSetChildren($existing, $setDef, $adet);
                        } else {
                            $childIds = DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $existing->No)->pluck('No')->toArray();
                            $touchedIds = array_merge($touchedIds, $childIds);
                        }
                    }
                } else {
                    $newNo = DB::table('tbSiparisSatir')->insertGetId([
                        'SiparisNo' => $siparisNo,
                        'Pazaryeri' => $pazaryeri,
                        'Magaza' => $magaza,
                        'SiparisTarihi' => $siparisTarihi,
                        'Musteri' => $musteri,
                        'UrunAdi' => $urunAdi,
                        'Adet' => $adet,
                        'MusteriNotu' => $musteriNotu,
                        'KargoSonTeslim' => $kargoSonTeslim,
                        'Kategori' => $kategori,
                        'StokKodu' => $stokKodu,
                        'Durum' => 'UretimBekliyor',
                        'Aktif' => 1,
                        'EslesenUrunNo' => $eslesenUrunNo,
                        'EslesenUrunTur' => $eslesenUrunTur,
                        'EslesmePuani' => $eslesmePuani,
                        'EslesmeYontemi' => $eslesmeYontemi,
                        'SetMi' => 0,
                        'SetNo' => null,
                        'AnaSetSatirNo' => null,
                        'YuklemeTarihi' => now(),
                    ], 'No');

                    $touchedIds[] = $newNo;
                    $inserted++;

                    // Set Logic Insert
                    if (isset($sets[$urunAdiLower])) {
                        $setDef = $sets[$urunAdiLower];
                        $newItem = DB::table('tbSiparisSatir')->where('No', $newNo)->first();
                        DB::table('tbSiparisSatir')->where('No', $newNo)->update([
                            'SetMi' => 1, 'SetNo' => $setDef->No,
                            'EslesenUrunNo' => null, 'EslesenUrunTur' => null, 'EslesmeYontemi' => 'Set'
                        ]);
                        $this->createSetChildren($newItem, $setDef, $adet);
                    }
                }
            }

            // Pasif hale getir
            if (!empty($touchedIds)) {
                $passivated = DB::table('tbSiparisSatir')
                    ->where('Aktif', 1)
                    ->whereIn('Durum', ['UretimBekliyor', 'StokKarsilandi'])
                    ->whereNotIn('No', $touchedIds)
                    ->update(['Durum' => 'Pasif', 'Aktif' => 0]);

                $pendingAlerts = DB::table('tbSiparisSatir')
                    ->where('Aktif', 1)
                    ->where('Durum', 'IsEmriVerildi')
                    ->whereNotIn('No', $touchedIds)
                    ->get();

                foreach ($pendingAlerts as $pa) {
                    $pendingWorkOrdersAlert[] = [
                        'no' => $pa->No,
                        'siparisNo' => $pa->SiparisNo,
                        'urunAdi' => $pa->UrunAdi,
                        'gorevNo' => $pa->GorevNo ?? 0
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Yükleme tamamlandı.',
                'inserted' => $inserted,
                'updated' => $updated,
                'passivated' => $passivated,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'pendingWorkOrders' => $pendingWorkOrdersAlert,
                'stokKoduKaydedilecekler' => $newStockCodes
            ];
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    private function createSetChildren($parentItem, $setDef, $multiplier)
    {
        $contents = DB::table('tbSetIcerikleri')->where('SetNo', $setDef->No)->get();
        foreach ($contents as $content) {
            $childProduct = DB::table('tbUrunler')->where('No', $content->UrunNo)->first();
            if (!$childProduct) continue;

            DB::table('tbSiparisSatir')->insert([
                'SiparisNo' => $parentItem->SiparisNo,
                'Pazaryeri' => $parentItem->Pazaryeri,
                'Magaza' => $parentItem->Magaza,
                'SiparisTarihi' => $parentItem->SiparisTarihi,
                'Musteri' => $parentItem->Musteri,
                'UrunAdi' => $childProduct->SistemAdi ?? $childProduct->UrunID,
                'Adet' => $content->Adet * $multiplier,
                'MusteriNotu' => $parentItem->MusteriNotu,
                'KargoSonTeslim' => $parentItem->KargoSonTeslim,
                'Kategori' => $parentItem->Kategori,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => $childProduct->No,
                'EslesenUrunTur' => 'Nihai',
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Set',
                'SetMi' => 0,
                'SetNo' => $setDef->No,
                'AnaSetSatirNo' => $parentItem->No,
                'YuklemeTarihi' => now(),
            ]);
        }
    }

    private function parseDate($str)
    {
        if (empty($str)) return null;
        try {
            return Carbon::parse($str)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
