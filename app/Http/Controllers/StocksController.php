<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StocksController extends Controller
{
    public function getStocks(Request $request)
    {
        $query = DB::table('tbBolumAraStok as s')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->select('s.No', 's.BolumAdiNo', 's.AraUrunAdiNo', 's.Adet', 's.TamponMiktar', 's.UrunIDNo',
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi"));

        if ($request->filled('department_id')) {
            $query->where('s.BolumAdiNo', $request->department_id);
        }
        if ($request->filled('component_type')) {
            $query->where('a.UrunCesidi', $request->component_type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('a.AraUrunAdi', 'like', "%$s%")
                  ->orWhere('b.BolumAdi', 'like', "%$s%");
            });
        }

        $sortField = $request->input('sort_by', 'No');
        $sortDir = $request->input('sort_dir', 'asc');
        $map = ['No' => 's.No', 'BolumAdi' => 'BolumAdi', 'AraUrunAdi' => 'AraUrunAdi', 'Adet' => 's.Adet', 'TamponMiktar' => 's.TamponMiktar'];
        $query->orderBy($map[$sortField] ?? 's.No', $sortDir);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $total = $query->count();
        $data = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return response()->json(['data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function getLookups()
    {
        $departments = DB::table('tbBolum')->select('No as id', 'BolumAdi as name')->orderBy('BolumAdi')->get();
        $componentTypes = DB::table('tbAraUrun')
            ->select('UrunCesidi')
            ->whereNotNull('UrunCesidi')->where('UrunCesidi', '!=', '')
            ->distinct()->orderBy('UrunCesidi')->pluck('UrunCesidi');
        $components = DB::table('tbAraUrun')->select('No as id', 'AraUrunAdi as name')->orderBy('AraUrunAdi')->get();

        return response()->json(['departments' => $departments, 'componentTypes' => $componentTypes, 'components' => $components]);
    }

    public function store(Request $request)
    {
        $araUrunNo = intval($request->input('component_id', $request->input('AraUrunAdiNo', 0)));
        $adet = intval($request->input('quantity', $request->input('Adet', 0)));
        $tampon = intval($request->input('buffer_quantity', $request->input('TamponMiktar', 0)));

        $existing = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->first();
        if ($existing) {
            DB::table('tbBolumAraStok')->where('No', $existing->No)->update([
                'Adet' => DB::raw("Adet + $adet"), 'TamponMiktar' => DB::raw("TamponMiktar + $tampon")
            ]);
        } else {
            $bolumAdiNo = intval(DB::table('tbAraUrun')->where('No', $araUrunNo)->value('BolumAdiNo') ?? 0);
            DB::table('tbBolumAraStok')->insert([
                'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                'AraUrunAdiNo' => $araUrunNo, 'Adet' => $adet, 'TamponMiktar' => $tampon
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $adet = intval($request->input('quantity', $request->input('Adet', 0)));
        $tampon = intval($request->input('buffer_quantity', $request->input('TamponMiktar', 0)));
        DB::table('tbBolumAraStok')->where('No', $id)->update(['Adet' => $adet, 'TamponMiktar' => $tampon]);
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('tbBolumAraStok')->where('No', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function resetBuffer()
    {
        DB::table('tbBolumAraStok')->update(['TamponMiktar' => DB::raw('Adet')]);
        return response()->json(['success' => true]);
    }
}
