<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;

class AdminComponentController extends Controller
{
    public function getComponents(Request $request)
    {
        $query = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->select('a.*', DB::raw("IFNULL(b.BolumAdi,'') as department_name"))
            ->orderByDesc('a.No');

        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo') && !$request->boolean('include_merged')) {
            $query->where(function ($q) {
                $q->whereNull('a.MergedIntoNo')->orWhere('a.MergedIntoNo', 0);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('a.AraUrunAdi', 'like', "%$search%")->orWhere('a.No', 'like', "%$search%");
            });
        }

        $data = $query->get()->map(fn($comp) => [
            'id' => $comp->No, 'name' => $comp->AraUrunAdi,
            'performance_score' => $comp->Performans, 'min_quantity' => $comp->MinAdet,
            'type' => $comp->UrunCesidi, 'path' => $comp->Yol,
            'image' => $comp->Resim ?? '', 'department_id' => $comp->BolumAdiNo,
            'department_name' => $comp->department_name,
            'merged_into_no' => intval($comp->MergedIntoNo ?? 0), 'merged_at' => $comp->MergedAt ?? null,
        ]);

        return response()->json(['data' => $data]);
    }

    public function storeComponent(Request $request)
    {
        $request->validate(['name' => 'required']);

        $path = $request->path ?? '';
        if ($request->filled('name_path')) {
            $converted = $this->convertYolNamesToIds($request->name_path);
            if (str_contains($converted, '#')) {
                return response()->json(['success' => false, 'message' => 'Ürün Yolu için girilen ürün adını kaydediniz...'], 422);
            }
            $path = $converted;
        }

        $imagePath = $request->image ?? '';
        if ($request->hasFile('image_file')) {
            $request->validate(['image_file' => 'mimes:jpg,jpeg,png|max:2048']);
            $imagePath = $this->saveLegacyProductImage($request->file('image_file'));
        }

        $no = DB::table('tbAraUrun')->insertGetId([
            'AraUrunAdi' => $request->name, 'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0, 'UrunCesidi' => $request->type ?? '',
            'Yol' => $path, 'Resim' => $imagePath, 'BolumAdiNo' => $request->department_id ?: null,
        ], 'No');

        if ($this->isFinalProductType($request->type ?? '')) {
            $this->urunlerTablosunaEkle($request->name);
        }

        return response()->json(['success' => true, 'message' => 'Ara ürün eklendi', 'id' => $no]);
    }

    public function updateComponent(Request $request, $id)
    {
        $update = [
            'AraUrunAdi' => $request->name, 'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0, 'UrunCesidi' => $request->type ?? '',
        ];
        if ($request->has('image')) $update['Resim'] = $request->image;
        if ($request->has('path')) $update['Yol'] = $request->path;
        if ($request->has('department_id')) {
            $departmentId = intval($request->input('department_id'));
            $update['BolumAdiNo'] = $departmentId > 0 ? $departmentId : null;
        }

        DB::table('tbAraUrun')->where('No', $id)->update($update);
        return response()->json(['message' => 'Ara ürün güncellendi']);
    }

    public function updateComponentBomPath(Request $request, $id)
    {
        $componentId = intval($id);
        $component = DB::table('tbAraUrun')->where('No', $componentId)->first();
        if (!$component) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
        }

        try {
            $path = $request->has('items')
                ? $this->normalizeBomPathItems($request->input('items'), $componentId)
                : $this->normalizeBomPathString((string) $request->input('path', ''), $componentId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        DB::table('tbAraUrun')->where('No', $componentId)->update(['Yol' => $path]);

        try {
            app(BomService::class)->personelGorevTabloGuncelle(strval($componentId));
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Ürün ağacı kaydedildi.', 'id' => $componentId, 'path' => $path]);
    }

    public function clearComponentBomPath($id)
    {
        $componentId = intval($id);
        $component = DB::table('tbAraUrun')->where('No', $componentId)->first();
        if (!$component) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
        }

        DB::table('tbAraUrun')->where('No', $componentId)->update(['Yol' => '']);

        try {
            app(BomService::class)->personelGorevTabloGuncelle(strval($componentId));
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Ürün ağacı temizlendi.', 'id' => $componentId, 'path' => '']);
    }

    public function deleteComponent($id)
    {
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

    public function getComponentBomPathNames($id)
    {
        try {
            $bomService = app(BomService::class);
            $idPath = $bomService->tumYolHazirla(strval($id));

            if (empty($idPath)) {
                return response()->json(['success' => true, 'namePath' => '', 'idPath' => '', 'edges' => [], 'replaceables' => []]);
            }

            $namePath = $this->idPathToNamePath($idPath);
            $edges = $this->idPathToBomEdges($idPath);

            $targetNames = collect($edges)->pluck('target_name')->unique()->all();
            $replaceables = collect($edges)->pluck('source_name')->unique()
                ->filter(fn ($source) => !in_array($source, $targetNames, true))->sort()->values()->all();

            return response()->json(['success' => true, 'namePath' => $namePath, 'idPath' => $idPath, 'edges' => $edges, 'replaceables' => $replaceables]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Helper methods (extracted from original AdminDatabaseController) ──

    private function idPathToNamePath(string $idPath): string
    {
        if (empty($idPath)) return '';
        $allNos = [];
        foreach (explode(':', $idPath) as $seg) {
            foreach (explode('-', $seg) as $i => $part) {
                if ($i < 2) $allNos[] = intval($part);
            }
        }
        $nameMap = DB::table('tbAraUrun')->whereIn('No', array_unique($allNos))->pluck('AraUrunAdi', 'No')->toArray();
        $result = [];
        foreach (explode(':', $idPath) as $seg) {
            $parts = explode('-', $seg);
            if (count($parts) >= 3) {
                $result[] = ($nameMap[intval($parts[0])] ?? $parts[0]) . '-' . ($nameMap[intval($parts[1])] ?? $parts[1]) . '-' . $parts[2];
            }
        }
        return implode(':', $result);
    }

    private function idPathToBomEdges(string $idPath): array
    {
        if (empty($idPath)) return [];
        $segments = [];
        $allNos = [];
        foreach (explode(':', $idPath) as $seg) {
            $parts = explode('-', trim($seg));
            if (count($parts) < 3) continue;
            $sourceId = intval($parts[0]);
            $targetId = intval($parts[1]);
            if ($sourceId <= 0 || $targetId <= 0) continue;
            $segments[] = ['source_id' => $sourceId, 'target_id' => $targetId, 'quantity' => $parts[2]];
            $allNos[] = $sourceId;
            $allNos[] = $targetId;
        }
        if (empty($segments)) return [];
        $componentMap = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->whereIn('a.No', array_unique($allNos))
            ->select('a.No', 'a.AraUrunAdi', 'a.UrunCesidi', DB::raw("IFNULL(b.BolumAdi, '') as department_name"))
            ->get()->keyBy('No');
        return array_map(fn ($edge) => [
            'source_id' => $edge['source_id'], 'source_name' => $componentMap->get($edge['source_id'])->AraUrunAdi ?? strval($edge['source_id']),
            'source_department_name' => $componentMap->get($edge['source_id'])->department_name ?? '', 'source_type' => $componentMap->get($edge['source_id'])->UrunCesidi ?? '',
            'target_id' => $edge['target_id'], 'target_name' => $componentMap->get($edge['target_id'])->AraUrunAdi ?? strval($edge['target_id']),
            'target_department_name' => $componentMap->get($edge['target_id'])->department_name ?? '', 'target_type' => $componentMap->get($edge['target_id'])->UrunCesidi ?? '',
            'quantity' => $edge['quantity'],
        ], $segments);
    }

    private function convertYolNamesToIds(string $nameYol): string
    {
        if (empty(trim($nameYol))) return '';
        $result = [];
        foreach (explode(':', $nameYol) as $seg) {
            $seg = trim($seg);
            if (empty($seg)) continue;
            $parts = explode('-', $seg);
            if (count($parts) >= 2) {
                $source = trim($parts[0]);
                $no = ctype_digit($source) ? $source : DB::table('tbAraUrun')->where('AraUrunAdi', $source)->value('No');
                $result[] = $no ? $no . '-' . $parts[1] : '#' . $source . '-' . $parts[1];
            }
        }
        return implode(':', $result);
    }

    private function normalizeBomPathString(string $path, int $ownerId): string
    {
        $items = [];
        foreach (collect(explode(':', $path))->map(fn ($s) => trim($s))->filter(fn ($s) => $s !== '')->values()->all() as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $items[] = ['component_id' => $parts[0] ?? null, 'quantity' => $parts[1] ?? 1];
        }
        return $this->normalizeBomPathItems($items, $ownerId);
    }

    private function normalizeBomPathItems(mixed $items, int $ownerId): string
    {
        if (!is_array($items)) throw new \InvalidArgumentException('Ürün ağacı satırları geçersiz.');
        $segments = [];
        $childIds = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) throw new \InvalidArgumentException('Ürün ağacı satırları geçersiz.');
            $rawComponentId = trim((string) ($item['component_id'] ?? $item['id'] ?? $item['No'] ?? ''));
            $rawQuantity = trim((string) ($item['quantity'] ?? $item['adet'] ?? $item['Adet'] ?? '1'));
            if ($rawComponentId === '') continue;
            $componentId = intval($rawComponentId);
            $quantity = intval($rawQuantity);
            if ($componentId <= 0) throw new \InvalidArgumentException('Alt parça seçimi geçersiz.');
            if ($componentId === $ownerId) throw new \InvalidArgumentException('Bir ürün kendi alt parçası olamaz.');
            if ($quantity <= 0) throw new \InvalidArgumentException('Adet sıfırdan büyük olmalı.');
            if (isset($seen[$componentId])) throw new \InvalidArgumentException('Aynı alt parça birden fazla eklenemez.');
            if ($this->wouldCreateBomCycle($ownerId, $componentId)) throw new \InvalidArgumentException('Bu seçim ürün ağacında döngü oluşturur.');
            $seen[$componentId] = true;
            $childIds[] = $componentId;
            $segments[] = $componentId . '-' . $quantity;
        }
        if ($childIds) {
            $existingIds = DB::table('tbAraUrun')->whereIn('No', $childIds)->pluck('No')->map(fn ($v) => intval($v))->all();
            $missingIds = array_diff($childIds, $existingIds);
            if ($missingIds) throw new \InvalidArgumentException('Seçilen alt parçalardan biri bulunamadı.');
        }
        return implode(':', $segments);
    }

    private function wouldCreateBomCycle(int $ownerId, int $candidateChildId): bool
    {
        if ($ownerId === $candidateChildId) return true;
        return $this->bomDescendantContains($candidateChildId, $ownerId, []);
    }

    private function bomDescendantContains(int $componentId, int $needleId, array $visited): bool
    {
        if (isset($visited[$componentId])) return false;
        $visited[$componentId] = true;
        $path = (string) (DB::table('tbAraUrun')->where('No', $componentId)->value('Yol') ?? '');
        foreach (collect(explode(':', $path))->map(fn ($s) => trim($s))->filter(fn ($s) => $s !== '')->values()->all() as $segment) {
            $childId = intval(explode('-', $segment)[0] ?? 0);
            if ($childId <= 0) continue;
            if ($childId === $needleId) return true;
            if ($this->bomDescendantContains($childId, $needleId, $visited)) return true;
        }
        return false;
    }

    private function saveLegacyProductImage($file): string
    {
        $fileName = basename($file->getClientOriginalName());
        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
        $targetDir = $documentRoot !== '' && is_dir($documentRoot) ? $documentRoot . DIRECTORY_SEPARATOR . 'Resimler' : public_path('Resimler');
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $file->move($targetDir, $fileName);
        return $fileName;
    }

    private function isFinalProductType(?string $type): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', strtr((string) $type, ['İ'=>'i','I'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ş'=>'s','ş'=>'s','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c'])));
        return in_array($normalized, ['nihayiurun', 'nihaiurun'], true);
    }

    private function urunlerTablosunaEkle(string $urunAdi): void
    {
        $araUrunler = DB::table('tbAraUrun')->where('AraUrunAdi', $urunAdi)->get();
        if ($araUrunler->isEmpty()) return;
        $bomService = app(BomService::class);
        foreach ($araUrunler as $araUrun) {
            $tumYol = $bomService->tumYolHazirla((string) $araUrun->No);
            DB::table('tbUrunler')->where('UrunID', $araUrun->AraUrunAdi)->delete();
            DB::table('tbUrunler')->insert([
                'UrunID' => $araUrun->AraUrunAdi, 'AraAdlarYol' => $tumYol,
                'SistemAdi' => $araUrun->AraUrunAdi, 'Resim' => $araUrun->Resim ?? '',
            ]);
        }
    }
}
