<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApprovalHelpers;
use App\Http\Controllers\Concerns\LogsWorkOrderEvents;
use App\Http\Controllers\Concerns\SerializesRecord;
use App\Services\BomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminPoolTaskController extends Controller
{
    use ApprovalHelpers, LogsWorkOrderEvents, SerializesRecord;

    public function getPoolTasks(Request $request)
    {
        $query = DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'bh.UrunIDNo', '=', 'u.No')
            ->select(
                'bh.No', 'bh.UrunIDNo', 'bh.AraUrunAdiNo', 'bh.Adet', 'bh.ToplamAdet',
                'bh.GorevBaslangicTarihi', 'bh.GorevBaslangicSaati', 'bh.Aciklama', 'bh.BolumAdiNo', 'bh.SiparisSatirNo',
                DB::raw("IFNULL(a.Yol, '') as component_path"),
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name"),
                DB::raw("IFNULL(u.UrunID, '') as product_name")
            );

        if ($request->filled('department_id')) {
            $query->where('bh.BolumAdiNo', intval($request->department_id));
        }

        $rows = $query->orderBy('bh.GorevBaslangicTarihi')->orderBy('bh.GorevBaslangicSaati')->get();

        $reservedByOrderAndComponent = $this->reservedStockMapForPoolRows($rows);
        $bomService = app(BomService::class);

        $rows = $rows->map(function ($row) use ($reservedByOrderAndComponent, $bomService) {
            $reservedFromStock = (int) ($reservedByOrderAndComponent[(int) ($row->SiparisSatirNo ?? 0)][(int) ($row->AraUrunAdiNo ?? 0)] ?? 0);
            $directChildReservedFromStock = $this->directChildReservedStockTotal($row, $reservedByOrderAndComponent);
            $hasOpenDescendantWork = $bomService->hasOpenDescendantWork(strval($row->AraUrunAdiNo ?? 0), $bomService->traceContextFromRecord($row));
            $assignableQuantity = $bomService->effectivePoolAssignableQuantity($row);
            $netProduction = (int) $row->ToplamAdet;
            $bomRequired = $netProduction + $reservedFromStock;

            return [
                'id' => $row->No, 'urun_id_no' => $row->UrunIDNo, 'ara_urun_no' => $row->AraUrunAdiNo,
                'adet' => $assignableQuantity, 'toplam_adet' => $netProduction,
                'atanabilir_adet' => $assignableQuantity, 'planlanabilir_adet' => $netProduction,
                'uretilecek_net_adet' => $netProduction, 'stoktan_ayrilan_adet' => $reservedFromStock,
                'alt_stoktan_ayrilan_adet' => $directChildReservedFromStock,
                'alt_gorev_bekliyor' => $hasOpenDescendantWork, 'bom_ihtiyac_adet' => $bomRequired,
                'requested_adet' => $bomRequired,
                'gorev_tarihi' => trim((string) ($row->GorevBaslangicTarihi ?? '')),
                'gorev_saati' => trim((string) ($row->GorevBaslangicSaati ?? '')),
                'aciklama' => trim((string) ($row->Aciklama ?? '')),
                'department_id' => $row->BolumAdiNo, 'department_name' => $row->department_name,
                'component_name' => $row->component_name,
                'component_has_children' => trim((string) ($row->component_path ?? '')) !== '',
                'product_name' => $row->product_name, 'siparis_satir_no' => $row->SiparisSatirNo,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function assignPoolTask(Request $request, $id)
    {
        $request->validate([
            'personel_no' => 'required|integer', 'adet' => 'nullable|integer|min:1',
            'gorev_tarihi' => 'nullable|date_format:Y-m-d',
        ]);

        $taskDate = $request->filled('gorev_tarihi')
            ? \Carbon\Carbon::createFromFormat('Y-m-d', (string) $request->input('gorev_tarihi'))->startOfDay()
            : now()->startOfDay();
        if ($taskDate->lt(now()->startOfDay())) {
            return response()->json(['success' => false, 'message' => 'Görev tarihi bugünden önce olamaz.'], 422);
        }

        $legacyTaskDate = $taskDate->format('d/m/Y');
        $legacyTaskDateTime = $legacyTaskDate . ' 00:00';

        $sonuc = DB::transaction(function () use ($id, $request, $legacyTaskDate, $legacyTaskDateTime) {
            $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
            if (!$havuz || intval($havuz->ToplamAdet) <= 0) {
                return ['success' => false, 'message' => 'Havuz görevi bulunamadı ya da tamamlanmış.', 'status' => 404];
            }

            $personel = DB::table('tbPersonel')->where('PersonelNo', intval($request->personel_no))->lockForUpdate()->first();
            if (!$personel) return ['success' => false, 'message' => 'Personel bulunamadı.', 'status' => 404];

            $havuzBolumAdiNo = intval($havuz->BolumAdiNo ?? 0);
            $personelBolumAdiNo = intval($personel->BolumAdiNo ?? 0);
            if ($havuzBolumAdiNo > 0 && $personelBolumAdiNo !== $havuzBolumAdiNo) {
                return ['success' => false, 'message' => 'Bu görev yalnızca aynı bölümdeki personele atanabilir.', 'status' => 422];
            }

            $yeniAdet = intval($request->adet ?? $havuz->ToplamAdet);
            $toplamAdet = intval($havuz->ToplamAdet);
            $mevcutAdet = intval($havuz->Adet);
            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($havuz);
            $atanabilirAdet = $bomService->effectivePoolAssignableQuantity($havuz, $mevcutAdet);

            if ($yeniAdet <= 0 || $yeniAdet > $toplamAdet) {
                return ['success' => false, 'message' => 'Girdiğiniz değer kalan görev adedinden fazla olamaz.', 'status' => 422];
            }

            $hazirAtanacakAdet = min($yeniAdet, $atanabilirAdet);
            $bekleyenAtanacakAdet = max(0, $yeniAdet - $hazirAtanacakAdet);

            $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
            $personelNo = intval($personel->PersonelNo);
            $tarih = $legacyTaskDateTime;

            $mevcutKayitQuery = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)->where('PersonelNo', $personelNo)
                ->whereRaw($this->productionReadyApprovalSql())
                ->where(function ($query) { $query->where('Adet', '>', 0)->orWhere('BekleyenAdet', '>', 0); })
                ->whereRaw('SUBSTR(GorevBaslamaTarihi, 1, 10) = ?', [$legacyTaskDate]);
            $bomService->scopeQueryToTrace($mevcutKayitQuery, $traceContext, true);
            $mevcutKayit = $mevcutKayitQuery->first();

            $assignedTaskNo = null;

            if ($mevcutKayit) {
                $mevcutToplam = max(0, intval($mevcutKayit->Adet ?? 0)) + max(0, intval($mevcutKayit->BekleyenAdet ?? 0)) + $yeniAdet;
                $split = $bomService->personnelTaskReadySplit($mevcutKayit, $mevcutToplam);
                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'Adet' => intval($split['ready']), 'BekleyenAdet' => intval($split['waiting']), 'Onay' => 'hazir',
                ]);
                $assignedTaskNo = intval($mevcutKayit->No ?? 0);
            } else {
                $assignedTaskNo = DB::table('tbPersonelGorev')->insertGetId(array_merge([
                    'UrunIDNo' => intval($havuz->UrunIDNo ?? 0), 'GorevBaslamaTarihi' => $tarih,
                    'PersonelNo' => $personelNo, 'Adet' => $hazirAtanacakAdet, 'BekleyenAdet' => $bekleyenAtanacakAdet,
                    'Onay' => 'hazir', 'AraUrunAdiNo' => $araUrunAdiNo, 'BolumAdiNo' => $havuz->BolumAdiNo,
                ], $bomService->buildTracePayload($traceContext)));
            }

            $newToplamAdet = $toplamAdet - $yeniAdet;
            $newAdet = max(0, $mevcutAdet - $hazirAtanacakAdet);
            if ($newToplamAdet <= 0) {
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->update(['ToplamAdet' => $newToplamAdet, 'Adet' => $newAdet]);
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            if ($assignedTaskNo > 0) {
                $assignedTask = DB::table('tbPersonelGorev')->where('No', $assignedTaskNo)->first();
                if ($assignedTask) {
                    $this->logTaskEvent('task_assigned_by_admin', $assignedTask, null, [
                        'next_step_human' => 'Atanan personelin hazir gorevi onaylayip uretime gecmesi bekleniyor.',
                        'payload_before' => $this->serializeRecord($havuz),
                        'payload_after' => $this->serializeRecord($assignedTask),
                        'context' => [
                            'assigned_amount' => $yeniAdet, 'ready_amount' => $hazirAtanacakAdet,
                            'waiting_amount' => $bekleyenAtanacakAdet, 'scheduled_date' => $legacyTaskDate,
                            'pool_no' => intval($havuz->No ?? 0), 'personnel_no' => $personelNo,
                        ],
                    ]);
                }
            }

            $message = $yeniAdet . ' adet görev personele atandı.';
            if ($bekleyenAtanacakAdet > 0) $message .= ' ' . $bekleyenAtanacakAdet . ' adet alt görev/stok tamamlanınca açılacak.';
            return ['success' => true, 'message' => $message, 'status' => 200];
        });

        return response()->json(['success' => $sonuc['success'] ?? false, 'message' => $sonuc['message']], $sonuc['status'] ?? (($sonuc['success'] ?? false) ? 200 : 422));
    }

    public function deletePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];

                $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
                $toplamAdet = intval($havuz->ToplamAdet);

                $bomService = app(BomService::class);
                $bomService->tamponStokKontrol(strval($araUrunAdiNo), $toplamAdet);
                DB::table('tbBolumHavuz')->where('No', $id)->delete();
                $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

                $sipSatirNo = intval($havuz->SiparisSatirNo ?? 0);
                if ($sipSatirNo > 0) {
                    $kalanHavuz = DB::table('tbBolumHavuz')->where('SiparisSatirNo', $sipSatirNo)->count();
                    $kalanGorev = DB::table('tbPersonelGorev')->where('SiparisSatirNo', $sipSatirNo)
                        ->where(function ($q) { $q->where('Adet', '>', 0)->orWhere('BekleyenAdet', '>', 0); })
                        ->where(function ($q) { $q->where('BekleyenAdet', '>', 0)->orWhereRaw($this->openApprovalSql()); })->count();
                    if ($kalanHavuz <= 0 && $kalanGorev <= 0) {
                        DB::table('tbSiparisSatir')->where('No', $sipSatirNo)->where('Durum', 'IsEmriVerildi')
                            ->update(['Durum' => 'UretimBekliyor', 'GorevNo' => null, 'IsEmriTarihi' => null, 'GuncellemeTarihi' => now()]);
                    }
                }
                return ['success' => true, 'message' => 'Havuz kaydı silindi ve tampon stok güncellendi.'];
            });
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updatePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id, $request) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];

                $bomService = app(BomService::class);
                $araUrunAdiNo = strval($havuz->AraUrunAdiNo);
                $newToplamAdet = intval($request->input('toplam_adet', $havuz->ToplamAdet));
                $newAciklama = $request->input('aciklama', $havuz->Aciklama ?? '');

                $yol = $bomService->tumYolHazirla($araUrunAdiNo);
                $uretimAdet = $bomService->uretimAdetBelirle($araUrunAdiNo, $yol, $newToplamAdet);
                $uretilebilecekUrunAdedi = $bomService->adetBelirle($araUrunAdiNo);
                if ($uretilebilecekUrunAdedi < 0 || $uretilebilecekUrunAdedi > $uretimAdet) $uretilebilecekUrunAdedi = $uretimAdet;

                if ($uretimAdet <= 0) return ['success' => false, 'message' => 'Toplam adet sıfırdan büyük olmalıdır.'];

                DB::table('tbBolumHavuz')->where('No', $id)->update(['Adet' => $uretilebilecekUrunAdedi, 'ToplamAdet' => $uretimAdet, 'Aciklama' => $newAciklama]);
                return ['success' => true, 'message' => 'Havuz kaydı güncellendi.', 'ara_urun_no' => $araUrunAdiNo];
            });

            if (($result['success'] ?? false) && !empty($result['ara_urun_no'])) {
                $bomService = app(BomService::class);
                $bomService->sonrakiUrunAdetleriniGuncelle($result['ara_urun_no']);
                $bomService->personelGorevTabloGuncelle($result['ara_urun_no']);
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function storePoolTask(Request $request)
    {
        try {
            $araUrunAdiNo = intval($request->input('ara_urun_no'));
            $urunIDNo = intval($request->input('urun_id_no', 0));
            $adet = intval($request->input('toplam_adet', $request->input('adet', 0)));
            $aciklama = $request->input('aciklama', '');

            if ($araUrunAdiNo <= 0 || $adet <= 0) {
                return response()->json(['success' => false, 'message' => 'Ara ürün no ve adet gerekli.'], 422);
            }

            $bomService = app(BomService::class);
            $araUrun = DB::table('tbAraUrun')->where('No', $araUrunAdiNo)->first();
            if (!$araUrun) return response()->json(['success' => false, 'message' => 'Ara ürün bulunamadı.'], 404);

            $bolumAdiNo = intval($araUrun->BolumAdiNo ?? 0);

            if ($urunIDNo <= 0) {
                $urunIDNo = intval(DB::table('tbUrunler')->whereRaw("AraAdlarYol LIKE ?", ['%' . $araUrunAdiNo . '%'])->where('No', '!=', 502)->value('No') ?? 10);
            }

            $yol = $bomService->tumYolHazirla(strval($araUrunAdiNo));
            $uretimAdet = $bomService->uretimAdetBelirle(strval($araUrunAdiNo), $yol, $adet);
            $uretilebilirAdet = $bomService->adetBelirle(strval($araUrunAdiNo));
            if ($uretilebilirAdet > $uretimAdet || $uretilebilirAdet < 0) $uretilebilirAdet = $uretimAdet;

            if ($uretimAdet > 0) {
                $guncelAciklama = $aciklama;
                if (!empty($guncelAciklama)) {
                    $bolumAdi = DB::table('tbBolum')->where('No', $bolumAdiNo)->value('BolumAdi') ?? '';
                    $guncelAciklama = $bolumAdi . ': ' . trim($guncelAciklama);
                }
                DB::table('tbBolumHavuz')->insert([
                    'UrunIDNo' => $urunIDNo, 'GorevBaslangicTarihi' => now()->format('d/m/Y'),
                    'GorevBaslangicSaati' => now()->format('H:i'), 'Adet' => $uretilebilirAdet,
                    'ToplamAdet' => $uretimAdet, 'BolumAdiNo' => $bolumAdiNo,
                    'Aciklama' => $guncelAciklama, 'AraUrunAdiNo' => $araUrunAdiNo,
                ]);
                $bomService->araStokTamponAzalt(strval($araUrunAdiNo), $uretimAdet);
                return response()->json(['success' => true, 'message' => 'Havuz kaydı eklendi.']);
            }
            return response()->json(['success' => false, 'message' => 'Üretim adeti sıfır veya yetersiz stok.'], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deletePoolTasksByProduct(Request $request)
    {
        try {
            $urunIDNo = intval($request->input('urun_id_no'));
            if ($urunIDNo <= 0) return response()->json(['success' => false, 'message' => 'Ürün No gerekli.']);

            $affectedAraNos = [];
            $result = DB::transaction(function () use ($urunIDNo, &$affectedAraNos) {
                $rows = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->get();
                foreach ($rows as $row) {
                    $araNo = intval($row->AraUrunAdiNo);
                    if (!in_array($araNo, $affectedAraNos)) $affectedAraNos[] = $araNo;
                    app(BomService::class)->tamponStokKontrol(strval($araNo), intval($row->ToplamAdet));
                }
                $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            if (($result['success'] ?? false) && !empty($affectedAraNos)) {
                $bomService = app(BomService::class);
                foreach (array_unique($affectedAraNos) as $araNo) $bomService->personelGorevTabloGuncelle(strval($araNo));
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteAllPoolTasks(Request $request)
    {
        try {
            $affectedAraNos = [];
            $result = DB::transaction(function () use ($request, &$affectedAraNos) {
                $query = DB::table('tbBolumHavuz');
                if ($request->filled('department_id')) $query->where('BolumAdiNo', intval($request->department_id));
                elseif ($request->filled('ara_urun_no')) $query->where('AraUrunAdiNo', intval($request->ara_urun_no));
                elseif ($request->filled('urun_id_no')) $query->where('UrunIDNo', intval($request->urun_id_no));

                $bomService = app(BomService::class);
                $rows = $query->get();
                foreach ($rows as $row) {
                    $araNo = intval($row->AraUrunAdiNo);
                    if (!in_array($araNo, $affectedAraNos)) $affectedAraNos[] = $araNo;
                    $bomService->tamponStokKontrol(strval($araNo), intval($row->ToplamAdet));
                }

                $delQuery = DB::table('tbBolumHavuz');
                if ($request->filled('department_id')) $delQuery->where('BolumAdiNo', intval($request->department_id));
                elseif ($request->filled('ara_urun_no')) $delQuery->where('AraUrunAdiNo', intval($request->ara_urun_no));
                elseif ($request->filled('urun_id_no')) $delQuery->where('UrunIDNo', intval($request->urun_id_no));
                $deleted = $delQuery->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            if (($result['success'] ?? false) && !empty($affectedAraNos)) {
                $bomService = app(BomService::class);
                foreach (array_unique($affectedAraNos) as $araNo) $bomService->personelGorevTabloGuncelle(strval($araNo));
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Helpers ──

    private function reservedStockMapForPoolRows($rows): array
    {
        if (!Schema::hasColumn('tbSiparisSatir', 'TamponDusumleri')) return [];
        $orderItemNos = $rows->pluck('SiparisSatirNo')->map(fn ($no) => (int) $no)->filter(fn ($no) => $no > 0)->unique()->values()->all();
        if (empty($orderItemNos)) return [];
        $tamponRows = DB::table('tbSiparisSatir')->whereIn('No', $orderItemNos)->pluck('TamponDusumleri', 'No');
        $map = [];
        foreach ($tamponRows as $satirNo => $json) {
            $decoded = json_decode((string) $json, true);
            if (!is_array($decoded)) continue;
            foreach ($decoded as $entry) {
                if (!is_array($entry)) continue;
                $componentNo = (int) ($entry['araNo'] ?? $entry['AraUrunAdiNo'] ?? $entry['ara_urun_no'] ?? 0);
                $quantity = (int) ($entry['adet'] ?? $entry['Adet'] ?? $entry['quantity'] ?? 0);
                if ($componentNo <= 0 || $quantity <= 0) continue;
                $satirKey = (int) $satirNo;
                $map[$satirKey][$componentNo] = (int) (($map[$satirKey][$componentNo] ?? 0) + $quantity);
            }
        }
        return $map;
    }

    private function directChildReservedStockTotal(object $poolRow, array $reservedByOrderAndComponent): int
    {
        $satirNo = (int) ($poolRow->SiparisSatirNo ?? 0);
        if ($satirNo <= 0 || empty($reservedByOrderAndComponent[$satirNo])) return 0;
        $reservedForOrder = $reservedByOrderAndComponent[$satirNo];
        $total = 0;
        foreach ($this->directChildComponentNos((string) ($poolRow->component_path ?? '')) as $childNo) {
            $total += (int) ($reservedForOrder[$childNo] ?? 0);
        }
        return $total;
    }

    private function directChildComponentNos(string $path): array
    {
        $path = trim($path);
        if ($path === '') return [];
        $children = [];
        foreach (explode(':', $path) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $childNo = (int) ($parts[0] ?? 0);
            if ($childNo > 0) $children[$childNo] = true;
        }
        return array_keys($children);
    }
}
