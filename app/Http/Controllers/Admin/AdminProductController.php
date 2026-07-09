<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BomService;
use App\Services\ProductMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminProductController extends Controller
{
    public function getProducts(Request $request)
    {
        $query = DB::table('tbUrunler')->orderByDesc('No');
        if (Schema::hasColumn('tbUrunler', 'MergedIntoNo') && !$request->boolean('include_merged')) {
            $query->where(function ($q) {
                $q->whereNull('MergedIntoNo')->orWhere('MergedIntoNo', 0);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('UrunID', 'like', "%$search%")->orWhere('SistemAdi', 'like', "%$search%");
            });
        }
        $data = $query->get()->map(fn($p) => [
            'id' => $p->No, 'name' => $p->UrunID, 'system_name' => $p->SistemAdi,
            'system_code' => $p->SistemKodu, 'path' => $p->AraAdlarYol,
            'image' => $p->Resim ?? '', 'merged_into_no' => intval($p->MergedIntoNo ?? 0), 'merged_at' => $p->MergedAt ?? null,
        ]);
        return response()->json(['data' => $data]);
    }

    public function storeProduct(Request $request)
    {
        $name = $request->input('name', $request->input('UrunID'));
        if (!$name) return response()->json(['message' => 'Ürün adı gerekli'], 422);

        $no = DB::table('tbUrunler')->insertGetId([
            'UrunID' => $name, 'SistemAdi' => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu' => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ], 'No');
        return response()->json(['success' => true, 'message' => 'Ürün eklendi', 'id' => $no]);
    }

    public function previewProductMerge(Request $request, ProductMergeService $mergeService)
    {
        $data = $request->validate([
            'merge_type' => 'required|string', 'source_id' => 'required|integer|min:1',
            'target_id' => 'required|integer|min:1', 'include_linked_component' => 'sometimes|boolean',
        ]);
        try {
            return response()->json(['success' => true, 'preview' => $mergeService->preview(
                (string) $data['merge_type'], intval($data['source_id']), intval($data['target_id']),
                $request->boolean('include_linked_component')
            )]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Birleştirme önizlemesi hazırlanamadı: ' . $e->getMessage()], 500);
        }
    }

    public function mergeProducts(Request $request, ProductMergeService $mergeService)
    {
        $data = $request->validate([
            'merge_type' => 'required|string', 'source_id' => 'required|integer|min:1',
            'target_id' => 'required|integer|min:1', 'include_linked_component' => 'sometimes|boolean',
            'confirm' => 'required|boolean',
        ]);
        if (!$request->boolean('confirm')) {
            return response()->json(['success' => false, 'message' => 'Birleştirme onayı gerekli.'], 422);
        }
        try {
            $result = $mergeService->merge(
                (string) $data['merge_type'], intval($data['source_id']), intval($data['target_id']),
                $request->user(), $request->boolean('include_linked_component')
            );
            return response()->json(['success' => true, 'message' => 'Ürün birleştirme tamamlandı.', 'result' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Birleştirme tamamlanamadı: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request, $id)
    {
        $name = $request->input('name', $request->input('UrunID'));
        if (empty(trim((string) $name))) return response()->json(['message' => 'Ürün adı boş olamaz.'], 422);

        DB::table('tbUrunler')->where('No', $id)->update([
            'UrunID' => $name, 'SistemAdi' => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu' => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ]);
        return response()->json(['success' => true, 'message' => 'Ürün güncellendi']);
    }

    public function deleteProduct($id)
    {
        $urun = DB::table('tbUrunler')->where('No', $id)->first();
        if (!$urun) return response()->json(['message' => 'Ürün bulunamadı.'], 404);

        $aktifSiparisSayisi = DB::table('tbSiparisSatir')
            ->where('UrunID', $urun->UrunID)
            ->whereNotIn('Durum', ['Tamamlandi', 'Iptal', 'StokKarsilandi', 'UretimdenKarsilaniyor'])
            ->count();
        if ($aktifSiparisSayisi > 0) {
            return response()->json(['message' => "Bu ürünün {$aktifSiparisSayisi} aktif siparişi bulunuyor. Siparişler tamamlanmadan ürün silinemez."], 422);
        }

        $havuzSayisi = DB::table('tbBolumHavuz')->where('UrunIDNo', $id)->count();
        if ($havuzSayisi > 0) {
            return response()->json(['message' => "Bu ürün üretim havuzunda {$havuzSayisi} görevle kullanılıyor. Önce havuzu temizleyin."], 422);
        }

        DB::table('tbUrunler')->where('No', $id)->delete();
        return response()->json(['message' => 'Ürün silindi']);
    }

    public function uploadProductImage(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:2048']);
        if ($request->file('image')) {
            $path = $request->file('image')->store('products', 'public');
            DB::table('tbUrunler')->where('No', $id)->update(['Resim' => '/storage/' . $path]);
            return response()->json(['success' => true, 'message' => 'Resim başarıyla yüklendi', 'path' => '/storage/' . $path]);
        }
        return response()->json(['success' => false, 'message' => 'Resim alınamadı'], 400);
    }

    public function getProductBomPathNames($id)
    {
        try {
            $product = DB::table('tbUrunler')->where('No', $id)->first();
            if (!$product) return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);

            $idPath = $product->AraAdlarYol ?? '';
            $componentId = intval(DB::table('tbAraUrun')->where('AraUrunAdi', $product->UrunID)->value('No') ?? 0);

            if (empty(trim($idPath))) {
                return response()->json(['success' => true, 'product_name' => $product->UrunID, 'component_id' => $componentId, 'namePath' => '', 'idPath' => '', 'edges' => [], 'replaceables' => []]);
            }

            $namePath = $this->idPathToNamePath($idPath);
            $edges = $this->idPathToBomEdges($idPath);
            $targetNames = collect($edges)->pluck('target_name')->unique()->all();
            $replaceables = collect($edges)->pluck('source_name')->unique()
                ->filter(fn ($source) => !in_array($source, $targetNames, true))->sort()->values()->all();

            return response()->json(['success' => true, 'product_name' => $product->UrunID, 'component_id' => $componentId, 'namePath' => $namePath, 'idPath' => $idPath, 'edges' => $edges, 'replaceables' => $replaceables]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deriveProduct(Request $request)
    {
        try {
            $sourceId = intval($request->input('source_id'));
            $newNamePath = trim($request->input('new_name_path', ''));
            $imagePath = $request->input('image', '');

            if ($request->hasFile('image_file')) {
                $request->validate(['image_file' => 'mimes:jpg,jpeg,png|max:2048']);
                $imagePath = $this->saveLegacyProductImage($request->file('image_file'));
            }

            if ($sourceId <= 0 || empty($newNamePath)) {
                return response()->json(['success' => false, 'message' => 'Kaynak ürün ve yeni yol gerekli.'], 422);
            }

            $bomService = app(BomService::class);
            $sourceComponent = DB::table('tbAraUrun')->where('No', $sourceId)->first();
            if (!$sourceComponent) return response()->json(['success' => false, 'message' => 'Kaynak ara ürün bulunamadı.'], 404);

            $originalIdPath = $bomService->tumYolHazirla(strval($sourceId));
            $originalNamePath = $this->idPathToNamePath($originalIdPath);

            $origSegments = collect(explode(':', $originalNamePath))->map(fn ($s) => trim($s))->filter(fn ($s) => $s !== '')->values()->all();
            $newSegments = collect(explode(':', $newNamePath))->map(fn ($s) => trim($s))->filter(fn ($s) => $s !== '')->values()->all();
            if (empty($origSegments) || empty($newSegments)) {
                return response()->json(['success' => false, 'message' => 'Üretim yolu bulunamadı.'], 422);
            }

            $createdComponents = [];

            DB::transaction(function () use ($origSegments, $newSegments, $sourceId, $imagePath, &$createdComponents, $sourceComponent, $newNamePath) {
                $segmentCount = min(count($origSegments), count($newSegments));
                for ($i = 0; $i < $segmentCount; $i++) {
                    $origParts = explode('-', $origSegments[$i]);
                    $newParts = explode('-', $newSegments[$i]);
                    for ($j = 0; $j < 2 && $j < count($origParts) && $j < count($newParts); $j++) {
                        $origName = trim($origParts[$j]);
                        $newName = trim($newParts[$j]);
                        if ($origName === '' || $newName === '' || $origName === $newName) continue;

                        $existing = DB::table('tbAraUrun')->where('AraUrunAdi', $newName)->first();
                        if (!$existing) {
                            $origNo = intval(DB::table('tbAraUrun')->where('AraUrunAdi', $origName)->value('No'));
                            $origRecord = $origNo > 0 ? DB::table('tbAraUrun')->where('No', $origNo)->first() : null;
                            if ($origRecord) {
                                $newYol = $this->findComponentYolInPath($newName, $newNamePath);
                                $resim = $origRecord->Resim;
                                if ($sourceComponent && $sourceComponent->AraUrunAdi === $origName && !empty($imagePath)) {
                                    $resim = $imagePath;
                                }
                                DB::table('tbAraUrun')->insert([
                                    'AraUrunAdi' => $newName, 'Performans' => $origRecord->Performans,
                                    'BolumAdiNo' => $origRecord->BolumAdiNo, 'MinAdet' => $origRecord->MinAdet,
                                    'UrunCesidi' => $origRecord->UrunCesidi, 'Resim' => $resim, 'Yol' => $newYol,
                                ]);
                                $createdComponents[] = $newName;
                            }
                        }
                    }
                }

                foreach (array_values(array_unique($createdComponents)) as $componentName) {
                    $component = DB::table('tbAraUrun')->where('AraUrunAdi', $componentName)->first();
                    if (!$component) continue;
                    if (!empty($component->Yol)) {
                        $convertedYol = $this->convertYolNamesToIds($component->Yol);
                        if (!str_contains($convertedYol, '#')) {
                            DB::table('tbAraUrun')->where('No', $component->No)->update(['Yol' => $convertedYol]);
                        }
                    }
                    $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', strtr((string) ($component->UrunCesidi ?? ''), ['İ'=>'i','I'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ş'=>'s','ş'=>'s','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c'])));
                    if (in_array($normalized, ['nihayiurun', 'nihaiurun'], true)) {
                        $this->urunlerTablosunaEkle($componentName);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => count($createdComponents) > 0
                    ? count($createdComponents) . ' yeni bileşen türetildi: ' . implode(', ', $createdComponents)
                    : 'Değişiklik yapılmadı (tüm bileşenler zaten mevcut).',
                'created' => $createdComponents
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Helpers ──

    private function idPathToNamePath(string $idPath): string
    {
        if (empty($idPath)) return '';
        $allNos = [];
        foreach (explode(':', $idPath) as $seg) {
            foreach (explode('-', $seg) as $i => $part) { if ($i < 2) $allNos[] = intval($part); }
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
            $sourceId = intval($parts[0]); $targetId = intval($parts[1]);
            if ($sourceId <= 0 || $targetId <= 0) continue;
            $segments[] = ['source_id' => $sourceId, 'target_id' => $targetId, 'quantity' => $parts[2]];
            $allNos[] = $sourceId; $allNos[] = $targetId;
        }
        if (empty($segments)) return [];
        $componentMap = DB::table('tbAraUrun as a')->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
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

    private function findComponentYolInPath(string $componentName, string $fullNamePath): string
    {
        $inputs = [];
        foreach (explode(':', $fullNamePath) as $seg) {
            $parts = explode('-', $seg);
            if (count($parts) >= 3 && $parts[1] === $componentName) {
                $inputs[] = $parts[0] . '-' . $parts[2];
            }
        }
        return implode(':', $inputs);
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

    private function urunlerTablosunaEkle(string $urunAdi): void
    {
        $araUrunler = DB::table('tbAraUrun')->where('AraUrunAdi', $urunAdi)->get();
        if ($araUrunler->isEmpty()) return;
        $bomService = app(BomService::class);
        foreach ($araUrunler as $araUrun) {
            $tumYol = $bomService->tumYolHazirla((string) $araUrun->No);
            DB::table('tbUrunler')->where('UrunID', $araUrun->AraUrunAdi)->delete();
            DB::table('tbUrunler')->insert(['UrunID' => $araUrun->AraUrunAdi, 'AraAdlarYol' => $tumYol, 'SistemAdi' => $araUrun->AraUrunAdi, 'Resim' => $araUrun->Resim ?? '']);
        }
    }
}
