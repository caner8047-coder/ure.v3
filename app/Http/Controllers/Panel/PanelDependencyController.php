<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\BomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelDependencyController extends Controller
{
    private function personelNo(Request $request): int
    {
        return intval($request->user()->PersonelNo ?? $request->user()->id ?? 0);
    }

    public function dependencyInfo(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $bomService = app(BomService::class);

        $task = DB::table('tbPersonelGorev')
            ->leftJoin('tbAraUrun as au', 'tbPersonelGorev.AraUrunAdiNo', '=', 'au.No')
            ->where('tbPersonelGorev.No', $id)
            ->where('tbPersonelGorev.PersonelNo', $personelNo)
            ->select('tbPersonelGorev.*', 'au.AraUrunAdi', 'au.Yol')
            ->first();

        if (!$task) return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);

        $componentNo = intval($task->AraUrunAdiNo);
        $path = trim((string) ($task->Yol ?? ''));

        if ($path === '') {
            return response()->json(['success' => true, 'dependencies' => [], 'message' => 'Alt bağımlılık yok.']);
        }

        $dependencies = [];
        foreach (explode(':', $path) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($p) => $p !== ''));
            $childNo = intval($parts[0] ?? 0);
            if ($childNo <= 0) continue;

            $child = DB::table('tbAraUrun')->where('No', $childNo)->first();
            if (!$child) continue;

            $stock = DB::table('tbBolumStok')
                ->where('AraUrunAdiNo', $childNo)
                ->where('BolumAdiNo', intval($task->BolumAdiNo ?? 0))
                ->first();

            $dependencies[] = [
                'component_no' => $childNo,
                'component_name' => $child->AraUrunAdi ?? '',
                'required_quantity' => intval($parts[1] ?? 1),
                'available_stock' => intval($stock->Adet ?? 0),
                'has_enough' => intval($stock->Adet ?? 0) >= intval($parts[1] ?? 1),
            ];
        }

        return response()->json(['success' => true, 'dependencies' => $dependencies]);
    }

    public function notifyDependency(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);

        $task = DB::table('tbPersonelGorev')
            ->where('No', $id)
            ->where('PersonelNo', $personelNo)
            ->first();

        if (!$task) return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);

        $componentNo = intval($task->AraUrunAdiNo);
        $component = DB::table('tbAraUrun')->where('No', $componentNo)->first();
        $componentName = $component->AraUrunAdi ?? 'Bilinmeyen';
        $bolumAdiNo = intval($task->BolumAdiNo ?? 0);

        $message = "{$componentName} parça/için stok eksik, üretim bekliyor.";

        DB::table('tbIletisim')->insert([
            'PersonelNo' => 0,
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
            'Mesaj' => $message,
            'Tarih' => now()->format('d/m/Y'),
        ]);

        return response()->json(['success' => true, 'message' => 'Bağımlılık bildirimi gönderildi.']);
    }
}
