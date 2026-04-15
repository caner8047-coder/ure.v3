<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BomService;

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
        $eskiAdet = $existing ? intval($existing->Adet) : 0;

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

        $yeniAdet = intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->value('Adet') ?? 0);

        // ASP.NET SonrakiUrunAdetleriniGuncelle2 ve personelGorevTabloGuncelle
        $bomService = app(BomService::class);
        $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $yeniAdet);
        $bomService->personelGorevTabloGuncelle(strval($araUrunNo));

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        $eskiAdet = $record ? intval($record->Adet) : 0;
        $araUrunNo = $record ? intval($record->AraUrunAdiNo) : 0;

        $adet = intval($request->input('quantity', $request->input('Adet', 0)));
        $tampon = intval($request->input('buffer_quantity', $request->input('TamponMiktar', 0)));
        DB::table('tbBolumAraStok')->where('No', $id)->update(['Adet' => $adet, 'TamponMiktar' => $tampon]);

        // Zincirleme havuz ve görev güncelleme
        if ($araUrunNo > 0) {
            $bomService = app(BomService::class);
            $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $adet);
            $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
        }

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        if ($record) {
            $araUrunNo = intval($record->AraUrunAdiNo);
            $eskiAdet = intval($record->Adet);

            DB::table('tbBolumAraStok')->where('No', $id)->delete();

            // Zincirleme güncelleme (silindiğinde 0'a düşer)
            if ($araUrunNo > 0) {
                $bomService = app(BomService::class);
                $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, 0);
                $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
            }
        } else {
            DB::table('tbBolumAraStok')->where('No', $id)->delete();
        }
        return response()->json(['success' => true]);
    }

    public function resetBuffer()
    {
        DB::table('tbBolumAraStok')->update(['TamponMiktar' => DB::raw('Adet')]);
        return response()->json(['success' => true]);
    }

    public function exportCsv(Request $request)
    {
        $query = DB::table('tbBolumAraStok as s')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->select(
                's.No', 
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi"),
                's.Adet', 
                's.TamponMiktar'
            );

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

        $stocks = $query->get();
        $csvFileName = 'Stoklar_' . date('Ymd_His') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($stocks) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['No', 'Bölüm', 'Ara Ürün', 'Ürün Çeşidi', 'Adet', 'Tampon', 'Kullanılabilir']);

            foreach ($stocks as $row) {
                $kullanilabilir = max(0, intval($row->Adet) - intval($row->TamponMiktar));
                fputcsv($file, [
                    $row->No,
                    $row->BolumAdi,
                    $row->AraUrunAdi,
                    $row->UrunCesidi,
                    $row->Adet,
                    $row->TamponMiktar,
                    $kullanilabilir
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
