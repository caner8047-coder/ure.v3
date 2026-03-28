<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    // PERSONNEL (Users — Laravel users table)
    // ==========================================
    public function getPersonnel(Request $request)
    {
        $query = User::orderBy('id', 'desc');
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('surname', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('id', 'like', "%$search%");
            });
        }

        $users = $query->get();
        $data = $users->map(function($user) {
            // Bölüm bilgisini tbBolum'den al
            $dept = null;
            if ($user->department_id) {
                $dept = DB::table('tbBolum')->where('No', $user->department_id)->first();
            }
            return [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'address' => $user->address,
                'phone' => $user->phone,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'department_name' => $dept ? $dept->BolumAdi : ''
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function storePersonnel(Request $request)
    {
        $request->validate(['name' => 'required', 'email' => 'required|email|unique:users,email']);
        $user = new User();
        $user->name = $request->name;
        $user->surname = $request->surname ?? '';
        $user->email = $request->email;
        $user->phone = $request->phone ?? '';
        $user->address = $request->address ?? '';
        $user->department_id = $request->department_id;
        $user->password = Hash::make('123');
        $user->save();
        return response()->json(['message' => 'Personel başarıyla eklendi', 'data' => $user]);
    }

    public function updatePersonnel(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->name = $request->name;
        $user->surname = $request->surname ?? '';
        $user->email = $request->email;
        $user->phone = $request->phone ?? '';
        $user->address = $request->address ?? '';
        if ($request->department_id) $user->department_id = $request->department_id;
        $user->save();
        return response()->json(['message' => 'Personel güncellendi']);
    }

    public function deletePersonnel($id)
    {
        User::destroy($id);
        return response()->json(['message' => 'Personel silindi']);
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
        $request->validate(['name' => 'required']);
        $no = DB::table('tbUrunler')->insertGetId([
            'UrunID' => $request->name,
            'SistemAdi' => $request->system_name ?? '',
            'SistemKodu' => $request->system_code ?? '',
            'AraAdlarYol' => $request->path ?? '',
        ], 'No');
        return response()->json(['message' => 'Ürün eklendi', 'id' => $no]);
    }

    public function updateProduct(Request $request, $id)
    {
        DB::table('tbUrunler')->where('No', $id)->update([
            'UrunID' => $request->name,
            'SistemAdi' => $request->system_name ?? '',
            'SistemKodu' => $request->system_code ?? '',
        ]);
        return response()->json(['message' => 'Ürün güncellendi']);
    }

    public function deleteProduct($id)
    {
        DB::table('tbUrunler')->where('No', $id)->delete();
        return response()->json(['message' => 'Ürün silindi']);
    }
}
