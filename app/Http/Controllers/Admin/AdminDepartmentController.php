<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDepartmentController extends Controller
{
    public function getDepartments(Request $request)
    {
        $pSub = DB::table('tbPersonel')->selectRaw('BolumAdiNo, COUNT(*) as cnt')->groupBy('BolumAdiNo');
        $aSub = DB::table('tbAraUrun')->selectRaw('BolumAdiNo, COUNT(*) as cnt')->groupBy('BolumAdiNo');

        $query = DB::table('tbBolum as b')
            ->leftJoinSub($pSub, 'pc', fn($j) => $j->on('b.No', '=', 'pc.BolumAdiNo'))
            ->leftJoinSub($aSub, 'ac', fn($j) => $j->on('b.No', '=', 'ac.BolumAdiNo'))
            ->select('b.No', 'b.BolumAdi',
                DB::raw('IFNULL(pc.cnt, 0) as personnel_count'),
                DB::raw('IFNULL(ac.cnt, 0) as component_count'))
            ->orderBy('b.No', 'asc');

        if ($search = $request->input('search')) {
            $query->where('b.BolumAdi', 'like', "%$search%")
                  ->orWhere('b.No', 'like', "%$search%");
        }

        $depts = $query->get()->map(fn($d) => [
            'id'               => $d->No,
            'name'             => $d->BolumAdi,
            'bolum_no'         => $d->No,
            'personnel_count'  => (int)$d->personnel_count,
            'component_count'  => (int)$d->component_count,
        ]);
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
        $request->validate(['name' => 'required|string|max:255']);
        DB::table('tbBolum')->where('No', $id)->update(['BolumAdi' => $request->name]);
        return response()->json(['message' => 'Bölüm güncellendi']);
    }

    public function deleteDepartment($id)
    {
        $personelSayisi = DB::table('tbPersonel')->where('BolumAdiNo', $id)->count();
        $araUrunSayisi  = DB::table('tbAraUrun')->where('BolumAdiNo', $id)->count();

        if ($personelSayisi > 0 || $araUrunSayisi > 0) {
            return response()->json([
                'message' => "Bu bölümde {$personelSayisi} personel ve {$araUrunSayisi} ara ürün kayıtlı. Önce bunları başka bir bölüme taşıyın."
            ], 422);
        }

        DB::table('tbBolum')->where('No', $id)->delete();
        return response()->json(['message' => 'Bölüm silindi']);
    }
}
