<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Personnel;
use Illuminate\Support\Facades\DB;
use App\Services\BomService;

class AdminDatabaseController extends Controller
{
    // ============================================
    // PRODUCT SETTINGS API (UrunOzellikleriAyarlari)
    // ============================================
    public function getProductSettingsLookups()
    {
        $urunler = DB::table('tbUrunler')
            ->whereNotIn('No', [10, 49, 50])
            ->select('No', 'UrunID')
            ->orderBy('UrunID')
            ->get();
            
        return response()->json(['success' => true, 'urunler' => $urunler]);
    }

    public function getProductSettingsDetails($urunNo)
    {
        $urun = DB::table('tbUrunler')->where('No', $urunNo)->first();
        if (!$urun) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.']);
        }

        $tablo = [];
        if (!empty($urun->AraAdlarYol)) {
            $parcalar = explode(':', $urun->AraAdlarYol);
            $seenAraUrun = [];
            foreach ($parcalar as $p) {
                if (trim($p) == '') continue;
                $parts = explode('-', $p);
                if (count($parts) >= 2) {
                    $araNo = intval($parts[0]);
                    $adet = intval($parts[1]);
                    
                    if (!in_array($araNo, $seenAraUrun)) {
                        $araUrunData = DB::table('tbAraUrun')->where('No', $araNo)->first();
                        if ($araUrunData) {
                            $tablo[] = [
                                'No' => $urunNo,
                                'UrunID' => $urun->UrunID,
                                'SistemAdi' => $urun->SistemAdi,
                                'SistemKodu' => $urun->SistemKodu,
                                'AraUrunNo' => $araNo,
                                'AraUrun' => $araUrunData->AraUrunAdi,
                                'Adet' => $adet,
                                'Performans' => $araUrunData->Performans
                            ];
                            $seenAraUrun[] = $araNo;
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true, 
            'sistemAdi' => $urun->SistemAdi,
            'sistemKodu' => $urun->SistemKodu,
            'tablo' => $tablo
        ]);
    }

    public function updateProductSettings(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            $urunNo = $request->input('urunNo');
            $sistemAdi = $request->input('sistemAdi');
            $sistemKodu = $request->input('sistemKodu');

            DB::transaction(function () use ($updates, $urunNo, $sistemAdi, $sistemKodu) {
                foreach ($updates as $upd) {
                    if (isset($upd['AraUrunNo']) && isset($upd['Performans'])) {
                        DB::table('tbAraUrun')
                            ->where('No', $upd['AraUrunNo'])
                            ->update(['Performans' => $upd['Performans']]);
                    }
                }

                if ($urunNo) {
                    DB::table('tbUrunler')
                        ->where('No', $urunNo)
                        ->update([
                            'SistemAdi' => empty($sistemAdi) ? null : $sistemAdi,
                            'SistemKodu' => empty($sistemKodu) ? null : $sistemKodu
                        ]);
                }
            });

            return response()->json(['success' => true, 'status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // PERSONNEL (tbPersonel)
    // ==========================================
    public function getPersonnel(Request $request)
    {
        $query = Personnel::query()->orderByDesc('PersonelNo');

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('Ad', 'like', "%$search%")
                  ->orWhere('Soyad', 'like', "%$search%")
                  ->orWhere('Mail', 'like', "%$search%")
                  ->orWhere('Telefon', 'like', "%$search%")
                  ->orWhere('PersonelNo', 'like', "%$search%");
            });
        }

        $personnel = $query->get();
        $data = $personnel->map(function($user) {
            $dept = null;
            if ((int) $user->BolumAdiNo > 0) {
                $dept = DB::table('tbBolum')->where('No', $user->BolumAdiNo)->first();
            }

            return [
                'id' => $user->PersonelNo,
                'name' => $user->Ad,
                'surname' => $user->Soyad,
                'address' => $user->Adres,
                'phone' => $user->Telefon,
                'email' => $user->Mail,
                'department_id' => (int) ($user->BolumAdiNo ?? 0) > 0 ? (int) $user->BolumAdiNo : null,
                'department_name' => $dept ? $dept->BolumAdi : ''
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Görevsiz personelleri getir (PersonelGorevsiz.aspx karşılığı)
     * Üzerinde aktif (tamamlanmamış) görev olmayan personelleri döndürür.
     */
    public function getIdlePersonnel()
    {
        $activePersonnelNos = DB::table('tbPersonelGorev')
            ->whereRaw("(Onay IS NULL OR Onay = 0 OR Onay = '0' OR LOWER(TRIM(CAST(Onay AS CHAR))) = 'false')")
            ->pluck('PersonelNo')
            ->unique()
            ->toArray();

        $idle = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->whereNotIn('p.PersonelNo', $activePersonnelNos)
            ->where('p.BolumAdiNo', '!=', 0)
            ->select(
                'p.PersonelNo',
                'p.Ad',
                'p.Soyad',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi")
            )
            ->orderBy('p.Ad')
            ->get();

        return response()->json(['personnel' => $idle]);
    }

    public function storePersonnel(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:tbPersonel,Mail',
        ]);

        $nextPersonnelNo = (int) (DB::table('tbPersonel')->max('PersonelNo') ?? 0) + 1;

        $user = new Personnel();
        $user->PersonelNo = $nextPersonnelNo;
        $user->Ad = $request->name;
        $user->Soyad = $request->surname ?? '';
        $user->Mail = $request->email;
        $user->Telefon = $request->phone ?? '';
        $user->Adres = $request->address ?? '';
        $user->BolumAdiNo = $request->department_id ?: 0;
        
        $pwd = $request->filled('new_password') ? $request->new_password : '123';
        $user->Sifre = hash('sha256', $pwd);
        
        $user->save();

        return response()->json(['message' => 'Personel başarıyla eklendi', 'data' => $user]);
    }

    public function updatePersonnel(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:tbPersonel,Mail,' . $id . ',PersonelNo',
        ]);

        $user = Personnel::findOrFail($id);
        $user->Ad = $request->name;
        $user->Soyad = $request->surname ?? '';
        $user->Mail = $request->email;
        $user->Telefon = $request->phone ?? '';
        $user->Adres = $request->address ?? '';
        $user->BolumAdiNo = $request->department_id ?: 0;

        // Şifre değişikliği
        if ($request->filled('new_password')) {
            $user->Sifre = hash('sha256', $request->new_password);
        }

        $user->save();

        return response()->json(['message' => 'Personel güncellendi']);
    }

    public function deletePersonnel($id)
    {
        Personnel::destroy($id);

        return response()->json(['message' => 'Personel silindi']);
    }

    public function getPersonnelWorkload()
    {
        $pendingTasks = DB::table('tbPersonelGorev')
            ->select('PersonelNo', DB::raw('COUNT(*) as aktifGorev'), DB::raw('SUM(BekleyenAdet) as bekleyenAdet'))
            ->where(function ($query) {
                $query->whereNull('Onay')
                    ->orWhere('Onay', 0)
                    ->orWhere('Onay', '0')
                    ->orWhereRaw("LOWER(TRIM(CAST(Onay AS CHAR))) = 'false'");
            })
            ->groupBy('PersonelNo');

        $rows = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoinSub($pendingTasks, 'pg', function ($join) {
                $join->on('p.PersonelNo', '=', 'pg.PersonelNo');
            })
            ->select(
                'p.PersonelNo',
                'p.Ad',
                'p.Soyad',
                'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw('IFNULL(pg.aktifGorev, 0) as aktifGorev'),
                DB::raw('IFNULL(pg.bekleyenAdet, 0) as bekleyenAdet')
            )
            ->orderBy('p.Ad')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function getPoolTasks(Request $request)
    {
        $query = DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'bh.UrunIDNo', '=', 'u.No')
            ->select(
                'bh.No',
                'bh.UrunIDNo',
                'bh.AraUrunAdiNo',
                'bh.Adet',
                'bh.ToplamAdet',
                'bh.GorevBaslangicTarihi',
                'bh.GorevBaslangicSaati',
                'bh.Aciklama',
                'bh.BolumAdiNo',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name"),
                DB::raw("IFNULL(u.UrunID, '') as product_name")
            );

        if ($request->filled('department_id')) {
            $query->where('bh.BolumAdiNo', intval($request->department_id));
        }

        $rows = $query
            ->orderBy('bh.GorevBaslangicTarihi')
            ->orderBy('bh.GorevBaslangicSaati')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->No,
                    'urun_id_no' => $row->UrunIDNo,
                    'ara_urun_no' => $row->AraUrunAdiNo,
                    'adet' => (int) $row->Adet,
                    'toplam_adet' => (int) $row->ToplamAdet,
                    'gorev_tarihi' => trim((string) ($row->GorevBaslangicTarihi ?? '')),
                    'gorev_saati' => trim((string) ($row->GorevBaslangicSaati ?? '')),
                    'aciklama' => trim((string) ($row->Aciklama ?? '')),
                    'department_id' => $row->BolumAdiNo,
                    'department_name' => $row->department_name,
                    'component_name' => $row->component_name,
                    'product_name' => $row->product_name,
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function assignPoolTask(Request $request, $id)
    {
        $request->validate([
            'personel_no' => 'required|integer',
            'adet' => 'nullable|integer|min:1',
        ]);

        $sonuc = DB::transaction(function () use ($id, $request) {
            $havuz = DB::table('tbBolumHavuz')
                ->where('No', $id)
                ->lockForUpdate()
                ->first();

            if (!$havuz || intval($havuz->ToplamAdet) <= 0) {
                return ['success' => false, 'message' => 'Havuz görevi bulunamadı ya da tamamlanmış.', 'status' => 404];
            }

            $personel = DB::table('tbPersonel')
                ->where('PersonelNo', intval($request->personel_no))
                ->lockForUpdate()
                ->first();

            if (!$personel) {
                return ['success' => false, 'message' => 'Personel bulunamadı.', 'status' => 404];
            }

            $yeniAdet = intval($request->adet ?? $havuz->ToplamAdet);
            $toplamAdet = intval($havuz->ToplamAdet);
            $mevcutAdet = intval($havuz->Adet);

            if ($yeniAdet <= 0 || $yeniAdet > $toplamAdet) {
                return ['success' => false, 'message' => 'Girdiğiniz değer toplam adetten fazla olamaz.', 'status' => 422];
            }

            // BomService ile üretilebilecek max adet hesapla (ASP.NET AdetBelirle)
            $bomService = app(BomService::class);
            $uretilebilecekMax = $bomService->adetBelirle(strval($havuz->AraUrunAdiNo));
            if ($uretilebilecekMax < 0) $uretilebilecekMax = $yeniAdet;

            // PersonelGorev tablosuna ekle/güncelle (ASP.NET PersonelGorevTablosunaEkle mantığı)
            $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
            $personelNo = intval($personel->PersonelNo);
            $tarih = now()->format('d/m/Y') . ' 00:00';

            $mevcutKayit = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $personelNo)
                ->where('Onay', 'true')
                ->where(DB::raw('LEFT(GorevBaslamaTarihi, 10)'), '=', now()->format('d/m/Y'))
                ->first();

            if ($mevcutKayit) {
                // Mevcut kaydı güncelle
                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'BekleyenAdet' => DB::raw("BekleyenAdet + {$yeniAdet}"),
                    'Adet' => DB::raw("Adet + {$yeniAdet}"),
                    'Onay' => 'false',
                ]);
            } else {
                // Yeni kayıt
                $effMax = min($uretilebilecekMax, $yeniAdet);
                DB::table('tbPersonelGorev')->insert([
                    'UrunIDNo' => $havuz->UrunIDNo,
                    'GorevBaslamaTarihi' => $tarih,
                    'PersonelNo' => $personelNo,
                    'Adet' => $yeniAdet,
                    'BekleyenAdet' => $yeniAdet,
                    'Onay' => 'false',
                    'AraUrunAdiNo' => $araUrunAdiNo,
                    'BolumAdiNo' => $havuz->BolumAdiNo,
                ]);
            }

            // Havuz güncelle (ASP.NET mantığı: ToplamAdet -= yeniAdet, Adet -= min(yeniAdet,max))
            $newToplamAdet = $toplamAdet - $yeniAdet;
            $adetReduction = ($uretilebilecekMax > $yeniAdet) ? $yeniAdet : $uretilebilecekMax;
            $newAdet = max(0, $mevcutAdet - $adetReduction);

            if ($newToplamAdet <= 0) {
                // Havuzdan sil (ASP.NET davranışı)
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->update([
                    'ToplamAdet' => $newToplamAdet,
                    'Adet' => $newAdet,
                ]);
            }

            // personelGorevTabloGuncelle — görev atama sonrası senkronizasyon
            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            return ['success' => true, 'message' => $yeniAdet . ' adet görev personele atandı.', 'status' => 200];
        });

        return response()->json(
            ['success' => $sonuc['success'] ?? false, 'message' => $sonuc['message']],
            $sonuc['status'] ?? (($sonuc['success'] ?? false) ? 200 : 422)
        );
    }

    // ==========================================
    // HAVUZ — SATIR SİLME + TAMPON STOK İADE
    // ==========================================
    public function deletePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) {
                    return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];
                }

                $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
                $toplamAdet = intval($havuz->ToplamAdet);

                // Tampon stoğu iade et (ASP.NET tamponStokKontrol mantığı)
                $stok = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunAdiNo)->first();
                if ($stok) {
                    $yeniTampon = intval($stok->TamponMiktar) + $toplamAdet;
                    // Tampon, stok adedini aşmamalı
                    $yeniTampon = min($yeniTampon, intval($stok->Adet));
                    DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunAdiNo)->update([
                        'TamponMiktar' => $yeniTampon
                    ]);
                }

                DB::table('tbBolumHavuz')->where('No', $id)->delete();

                // personelGorevTabloGuncelle — havuz değişimi sonrası senkronizasyon
                $bomService = app(BomService::class);
                $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

                return ['success' => true, 'message' => 'Havuz kaydı silindi ve tampon stok güncellendi.'];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — SATIR GÜNCELLEME
    // ==========================================
    public function updatePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id, $request) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) {
                    return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];
                }

                $newAdet = intval($request->input('adet', $havuz->Adet));
                $newToplamAdet = intval($request->input('toplam_adet', $havuz->ToplamAdet));

                DB::table('tbBolumHavuz')->where('No', $id)->update([
                    'Adet' => $newAdet,
                    'ToplamAdet' => $newToplamAdet,
                ]);

                return ['success' => true, 'message' => 'Havuz kaydı güncellendi.'];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — TOPLU SİLME (Ürün bazlı)
    // ==========================================
    public function deletePoolTasksByProduct(Request $request)
    {
        try {
            $urunIDNo = intval($request->input('urun_id_no'));
            if ($urunIDNo <= 0) {
                return response()->json(['success' => false, 'message' => 'Ürün No gerekli.']);
            }

            $result = DB::transaction(function () use ($urunIDNo) {
                $rows = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->get();

                foreach ($rows as $row) {
                    $stok = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', intval($row->AraUrunAdiNo))->first();
                    if ($stok) {
                        DB::table('tbBolumAraStok')->where('AraUrunAdiNo', intval($row->AraUrunAdiNo))->update([
                            'TamponMiktar' => intval($stok->TamponMiktar) + intval($row->ToplamAdet)
                        ]);
                    }
                }

                $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — TOPLU SİLME (Tüm / Filtreli)
    // ==========================================
    public function deleteAllPoolTasks(Request $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $query = DB::table('tbBolumHavuz');

                // ASP.NET btnDeleteAll_Click: görünüm moduna göre silme
                if ($request->filled('department_id')) {
                    $query->where('BolumAdiNo', intval($request->department_id));
                } elseif ($request->filled('ara_urun_no')) {
                    $query->where('AraUrunAdiNo', intval($request->ara_urun_no));
                } elseif ($request->filled('urun_id_no')) {
                    $query->where('UrunIDNo', intval($request->urun_id_no));
                }

                // Tampon stokları iade et (ASP.NET: Stoklar.tamponSifirla())
                $rows = $query->get();
                foreach ($rows as $row) {
                    $stok = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', intval($row->AraUrunAdiNo))->first();
                    if ($stok) {
                        DB::table('tbBolumAraStok')->where('AraUrunAdiNo', intval($row->AraUrunAdiNo))->update([
                            'TamponMiktar' => intval($stok->TamponMiktar) + intval($row->ToplamAdet)
                        ]);
                    }
                }

                // Silme işlemi (filtreli query'yi tekrar oluştur)
                $delQuery = DB::table('tbBolumHavuz');
                if ($request->filled('department_id')) {
                    $delQuery->where('BolumAdiNo', intval($request->department_id));
                } elseif ($request->filled('ara_urun_no')) {
                    $delQuery->where('AraUrunAdiNo', intval($request->ara_urun_no));
                } elseif ($request->filled('urun_id_no')) {
                    $delQuery->where('UrunIDNo', intval($request->urun_id_no));
                }

                $deleted = $delQuery->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // DEPARTMENTS (tbBolum)
    // ==========================================
    public function getDepartments(Request $request)
    {
        $query = DB::table('tbBolum')->orderBy('No', 'asc');
        if ($search = $request->input('search')) {
            $query->where('BolumAdi', 'like', "%$search%")
                  ->orWhere('No', 'like', "%$search%");
        }
        $depts = $query->get()->map(function($d) {
            return [
                'id' => $d->No,
                'name' => $d->BolumAdi,
                'bolum_no' => $d->No
            ];
        });
        return response()->json(['data' => $depts]);
    }

    public function storeDepartment(Request $request)
    {
        $request->validate(['name' => 'required']);
        $no = DB::table('tbBolum')->insertGetId([
            'BolumAdi' => $request->name
        ], 'No');
        return response()->json(['message' => 'Bölüm başarıyla eklendi', 'data' => ['id' => $no, 'name' => $request->name]]);
    }

    public function updateDepartment(Request $request, $id)
    {
        DB::table('tbBolum')->where('No', $id)->update(['BolumAdi' => $request->name]);
        return response()->json(['message' => 'Bölüm güncellendi']);
    }

    public function deleteDepartment($id)
    {
        DB::table('tbBolum')->where('No', $id)->delete();
        return response()->json(['message' => 'Bölüm silindi']);
    }

    // ==========================================
    // COMPONENTS (tbAraUrun — Ara Ürünler)
    // ==========================================
    public function getComponents(Request $request)
    {
        $query = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->select('a.*', DB::raw("IFNULL(b.BolumAdi,'') as department_name"))
            ->orderByDesc('a.No');

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('a.AraUrunAdi', 'like', "%$search%")
                  ->orWhere('a.No', 'like', "%$search%");
            });
        }

        $data = $query->get()->map(function($comp) {
            return [
                'id' => $comp->No,
                'name' => $comp->AraUrunAdi,
                'performance_score' => $comp->Performans,
                'min_quantity' => $comp->MinAdet,
                'type' => $comp->UrunCesidi,
                'path' => $comp->Yol,
                'image' => $comp->Resim,
                'department_id' => $comp->BolumAdiNo,
                'department_name' => $comp->department_name
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function storeComponent(Request $request)
    {
        $request->validate(['name' => 'required']);
        $no = DB::table('tbAraUrun')->insertGetId([
            'AraUrunAdi' => $request->name,
            'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0,
            'UrunCesidi' => $request->type ?? '',
            'Yol' => $request->path ?? '',
            'Resim' => $request->image ?? '',
            'BolumAdiNo' => $request->department_id ?: null,
        ], 'No');

        // Nihai Ürün ise tbUrunler'e de ekle
        if (($request->type ?? '') === 'Nihayi Ürün' || ($request->type ?? '') === 'Nihai Ürün') {
            $exists = DB::table('tbUrunler')->where('UrunID', $request->name)->exists();
            if (!$exists) {
                DB::table('tbUrunler')->insert([
                    'UrunID' => $request->name,
                    'AraAdlarYol' => $request->path ?? '',
                    'SistemAdi' => $request->name,
                ]);
            }
        }

        return response()->json(['message' => 'Ara ürün eklendi', 'id' => $no]);
    }

    public function updateComponent(Request $request, $id)
    {
        $update = [
            'AraUrunAdi' => $request->name,
            'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0,
            'UrunCesidi' => $request->type ?? '',
        ];
        if ($request->has('image')) $update['Resim'] = $request->image;
        if ($request->has('path')) $update['Yol'] = $request->path;
        if ($request->department_id) $update['BolumAdiNo'] = $request->department_id;

        DB::table('tbAraUrun')->where('No', $id)->update($update);
        return response()->json(['message' => 'Ara ürün güncellendi']);
    }

    public function deleteComponent($id)
    {
        // Başka ürünün yolunda kullanılıyor mu kontrol et
        $isInUse = DB::table('tbAraUrun')
            ->where('Yol', 'LIKE', '%:' . $id . '-%')
            ->orWhere('Yol', 'LIKE', $id . '-%')
            ->exists();

        if ($isInUse) {
            return response()->json(['message' => 'Bu ürün farklı bir üründe yol olarak kullanıldığı için silinemez'], 422);
        }

        $comp = DB::table('tbAraUrun')->where('No', $id)->first();
        if ($comp && ($comp->UrunCesidi === 'Nihayi Ürün' || $comp->UrunCesidi === 'Nihai Ürün')) {
            DB::table('tbUrunler')->where('UrunID', $comp->AraUrunAdi)->delete();
        }
        DB::table('tbAraUrun')->where('No', $id)->delete();

        return response()->json(['message' => 'Ara ürün silindi']);
    }
    
    // ==========================================
    // PRODUCTS (tbUrunler — Nihai Ürünler)
    // ==========================================
    public function getProducts(Request $request)
    {
        $query = DB::table('tbUrunler')->orderByDesc('No');
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('UrunID', 'like', "%$search%")
                  ->orWhere('SistemAdi', 'like', "%$search%");
            });
        }
        $data = $query->get()->map(function($p) {
            return [
                'id' => $p->No,
                'name' => $p->UrunID,
                'system_name' => $p->SistemAdi,
                'system_code' => $p->SistemKodu,
                'path' => $p->AraAdlarYol,
                'image' => $p->Resim ?? ''
            ];
        });
        return response()->json(['data' => $data]);
    }

    public function storeProduct(Request $request)
    {
        $name = $request->input('name', $request->input('UrunID'));
        if (!$name) {
            return response()->json(['message' => 'Ürün adı gerekli'], 422);
        }

        $no = DB::table('tbUrunler')->insertGetId([
            'UrunID' => $name,
            'SistemAdi' => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu' => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ], 'No');
        return response()->json(['success' => true, 'message' => 'Ürün eklendi', 'id' => $no]);
    }

    public function updateProduct(Request $request, $id)
    {
        $name = $request->input('name', $request->input('UrunID'));
        
        DB::table('tbUrunler')->where('No', $id)->update([
            'UrunID' => $name,
            'SistemAdi' => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu' => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ]);
        return response()->json(['success' => true, 'message' => 'Ürün güncellendi']);
    }

    public function deleteProduct($id)
    {
        DB::table('tbUrunler')->where('No', $id)->delete();
        return response()->json(['message' => 'Ürün silindi']);
    }

    // ==========================================
    // RESİM YÜKLEME 
    // ==========================================
    public function uploadProductImage(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:2048']); // Max 2MB
        
        if ($request->file('image')) {
            $path = $request->file('image')->store('products', 'public');
            
            DB::table('tbUrunler')->where('No', $id)->update([
                'Resim' => '/storage/' . $path
            ]);
            
            return response()->json(['success' => true, 'message' => 'Resim başarıyla yüklendi', 'path' => '/storage/' . $path]);
        }
        
        return response()->json(['success' => false, 'message' => 'Resim alınamadı'], 400);
    }
}
