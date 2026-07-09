<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApprovalHelpers;
use App\Http\Controllers\Concerns\LogsWorkOrderEvents;
use App\Http\Controllers\Concerns\SerializesRecord;
use App\Services\BomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelTaskController extends Controller
{
    use ApprovalHelpers, LogsWorkOrderEvents, SerializesRecord;

    private function personelNo(Request $request): int
    {
        return intval($request->user()->PersonelNo ?? $request->user()->id ?? 0);
    }

    private function nullableLegacyColumnSql(string $table, string $column, string $alias): string
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            return "IFNULL({$alias}.{$column}, '')";
        }
        return "''";
    }

    private function legacyDateOrderSql(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "datetime(COALESCE({$column}, '01/01/2000 00:00'), ' localtime')";
        }
        return "STR_TO_DATE(IFNULL({$column}, '01/01/2000'), '%d/%m/%Y %H:%i')";
    }

    private function dueTaskDateSql(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "(TRIM(COALESCE({$column}, '')) <> '' AND datetime({$column}, 'localtime') <= datetime('now'))";
        }
        return "(TRIM(IFNULL({$column}, '')) <> '' AND STR_TO_DATE({$column}, '%d/%m/%Y %H:%i') <= NOW())";
    }

    private function whereTaskDue($query, string $column)
    {
        return $query->whereRaw($this->dueTaskDateSql($column));
    }

    private function isTaskDue(?string $startedAt): bool
    {
        if (empty(trim((string) $startedAt))) return false;
        try {
            $date = \Carbon\Carbon::createFromFormat('d/m/Y H:i', trim((string) $startedAt));
            return $date->lte(now());
        } catch (\Throwable) {
            return false;
        }
    }

    private function taskDateKey(mixed $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') return '9999';
        try {
            $date = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $trimmed);
            return $date->format('YmdHi');
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    private function performanceMinutes(?string $startedAt, ?string $finishedAt): int
    {
        if (empty($startedAt) || empty($finishedAt)) return 0;
        try {
            $start = \Carbon\Carbon::createFromFormat('d/m/Y H:i', trim($startedAt));
            $end = \Carbon\Carbon::createFromFormat('d/m/Y H:i', trim($finishedAt));
            return max(0, (int) $start->diffInMinutes($end));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function enrichTaskImages($tasks)
    {
        if ($tasks instanceof \Illuminate\Support\Collection) {
            return $tasks->map(fn ($task) => $this->enrichTaskImage($task))->values();
        }
        return collect($tasks)->map(fn ($task) => $this->enrichTaskImage($task))->values();
    }

    private function enrichTaskImage(object $task): object
    {
        if (!empty($task->Resim)) return $task;
        $componentNo = intval($task->AraUrunAdiNo ?? 0);
        if ($componentNo > 0) {
            $task->Resim = $this->resolveTaskImageFromBom($componentNo);
        }
        return $task;
    }

    private function resolveTaskImageFromBom(int $componentNo): string
    {
        $component = DB::table('tbAraUrun')->where('No', $componentNo)->first();
        if (!$component) return '';
        if (!empty($component->Resim)) return $component->Resim;
        $path = trim((string) ($component->Yol ?? ''));
        if ($path === '') return '';
        $childNos = [];
        foreach (explode(':', $path) as $seg) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $seg)), fn ($p) => $p !== ''));
            $childNo = intval($parts[0] ?? 0);
            if ($childNo > 0) $childNos[] = $childNo;
        }
        foreach ($childNos as $childNo) {
            $img = $this->resolveTaskImageFromProductPath($childNo);
            if ($img !== '') return $img;
        }
        return '';
    }

    private function resolveTaskImageFromProductPath(int $componentNo): string
    {
        $component = DB::table('tbAraUrun')->where('No', $componentNo)->first();
        if (!$component) return '';
        if (!empty($component->Resim)) return $component->Resim;
        $product = DB::table('tbUrunler')->where('UrunID', $component->AraUrunAdi ?? '')->first();
        if ($product && !empty($product->Resim)) return $product->Resim;
        return '';
    }

    private function refreshAssignedTaskReadinessForPersonnel(int $personelNo): void
    {
        try {
            $tasks = DB::table('tbPersonelGorev')
                ->where('PersonelNo', $personelNo)
                ->where('BekleyenAdet', '>', 0)
                ->where(function ($q) {
                    $q->whereRaw($this->assignedWaitingApprovalSql());
                })
                ->get();

            if ($tasks->isEmpty()) return;

            $bomService = app(BomService::class);
            foreach ($tasks as $task) {
                $bomService->personelGorevTabloGuncelle(strval($task->AraUrunAdiNo));
            }
        } catch (\Throwable) {}
    }

    private function readyTaskVisualGroupKey(object $task): string
    {
        $dateKey = $this->taskDateKey($task->GorevBaslamaTarihi ?? '');
        $componentNo = intval($task->AraUrunAdiNo ?? 0);
        return $dateKey . '-' . $componentNo;
    }

    // ── Public Methods ──

    public function dashboardStats(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $aktifGorevlerQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('BekleyenAdet', '>', 0)->whereRaw($this->activeProductionApprovalSql());
                    });
            })
            ->whereRaw($this->inProductionApprovalSql());
        $aktifGorevler = $this->whereTaskDue($aktifGorevlerQuery, 'GorevBaslamaTarihi')->count();

        $uretimeHazirQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)->where('Adet', '>', 0)->whereRaw($this->productionReadyApprovalSql());
        $uretimeHazir = $this->whereTaskDue($uretimeHazirQuery, 'GorevBaslamaTarihi')->count();

        $tamamlanan = DB::table('tbGorevler')->where('PersonelNo', $personelNo)->where('ToplamAdet', '>', 0)->count();

        $bekleyenAdetQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where(function ($q) { $q->where('Adet', '>', 0)->orWhere('BekleyenAdet', '>', 0); })
            ->where(function ($q) { $q->where('BekleyenAdet', '>', 0)->orWhereRaw($this->pendingApprovalSql()); });
        $bekleyenAdet = $this->whereTaskDue($bekleyenAdetQuery, 'GorevBaslamaTarihi')->sum('BekleyenAdet');

        $alinabilir = DB::table('tbBolumHavuz as bh')
            ->join('tbPersonel as p', 'bh.BolumAdiNo', '=', 'p.BolumAdiNo')
            ->where('p.PersonelNo', $personelNo)->where('bh.Adet', '>', 0)->count();

        return response()->json([
            'success' => true, 'aktifGorevler' => $aktifGorevler, 'uretimeHazir' => $uretimeHazir,
            'tamamlanan' => $tamamlanan, 'alinabilir' => $alinabilir, 'bekleyenAdet' => intval($bekleyenAdet),
        ]);
    }

    public function myTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $tasks = DB::select("
            SELECT pg.No, pg.AraUrunAdiNo, pg.Adet,
                   CASE WHEN COALESCE(pg.Adet, 0) > 0 THEN pg.Adet
                        WHEN COALESCE(pg.BekleyenAdet, 0) > 0 AND " . $this->activeProductionApprovalSql('pg.Onay') . " THEN pg.BekleyenAdet
                        ELSE pg.Adet END AS UretilebilirAdet,
                   pg.BekleyenAdet, (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi, IFNULL(pg.Onay, '') AS Onay,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi, IFNULL(b.BolumAdi,'') AS BolumAdi, IFNULL(u.UrunID,'') AS UrunAdi,
                   COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                   COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.PersonelNo = ?
              AND ((COALESCE(pg.Adet, 0) > 0 AND " . $this->inProductionApprovalSql('pg.Onay') . ")
                   OR (COALESCE(pg.Adet, 0) <= 0 AND COALESCE(pg.BekleyenAdet, 0) > 0 AND " . $this->activeProductionApprovalSql('pg.Onay') . "))
              AND " . $this->dueTaskDateSql('pg.GorevBaslamaTarihi') . "
            ORDER BY " . $this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . " DESC, pg.No DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($tasks)]);
    }

    public function taskDetail(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $task = DB::selectOne("
            SELECT pg.*,
                   CASE WHEN COALESCE(pg.Adet, 0) > 0 THEN pg.Adet
                        WHEN COALESCE(pg.BekleyenAdet, 0) > 0 AND " . $this->activeProductionApprovalSql('pg.Onay') . " THEN pg.BekleyenAdet
                        ELSE pg.Adet END AS UretilebilirAdet,
                   (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihiFormatted,
                   IFNULL(pg.Onay, '') AS Onay,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi, IFNULL(b.BolumAdi,'') AS BolumAdi, IFNULL(u.UrunID,'') AS UrunAdi,
                   COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                   COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.No = ? AND pg.PersonelNo = ?
              AND (COALESCE(pg.Adet, 0) > 0 OR COALESCE(pg.BekleyenAdet, 0) > 0)
              AND (COALESCE(pg.BekleyenAdet, 0) > 0 OR " . $this->pendingApprovalSql('pg.Onay') . ")
              AND " . $this->dueTaskDateSql('pg.GorevBaslamaTarihi') . "
        ", [$id, $personelNo]);

        if (!$task) return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        return response()->json(['success' => true, 'task' => $this->enrichTaskImage($task)]);
    }

    public function deleteTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $sonuc = DB::transaction(function () use ($id, $personelNo) {
            $gorev = DB::table('tbPersonelGorev')->where('No', $id)->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$gorev) return ['success' => false, 'message' => 'Görev bulunamadı.'];
            if (!$this->isTaskDue($gorev->GorevBaslamaTarihi ?? null)) {
                return ['success' => false, 'message' => 'Bu görev seçilen tarihten önce personel ekranından değiştirilemez.'];
            }

            $araUrunAdiNo = intval($gorev->AraUrunAdiNo);
            $bekleyenAdet = max(0, intval($gorev->BekleyenAdet ?? 0));
            $iadeAdet = max(0, intval($gorev->Adet ?? 0)) + $bekleyenAdet;
            DB::table('tbPersonelGorev')->where('No', $id)->delete();

            if ($iadeAdet > 0) {
                $bomService = app(BomService::class);
                $existingPool = DB::table('tbBolumHavuz')
                    ->where('AraUrunAdiNo', $araUrunAdiNo)
                    ->where('BolumAdiNo', intval($gorev->BolumAdiNo ?? 0))
                    ->lockForUpdate()->first();

                if ($existingPool) {
                    DB::table('tbBolumHavuz')->where('No', $existingPool->No)->update([
                        'Adet' => max(0, intval($existingPool->Adet ?? 0)) + $iadeAdet,
                        'ToplamAdet' => intval($existingPool->ToplamAdet ?? 0) + $iadeAdet,
                    ]);
                } else {
                    DB::table('tbBolumHavuz')->insert([
                        'UrunIDNo' => intval($gorev->UrunIDNo ?? 0),
                        'GorevBaslangicTarihi' => now()->format('d/m/Y'),
                        'GorevBaslangicSaati' => now()->format('H:i'),
                        'Adet' => $iadeAdet, 'ToplamAdet' => $iadeAdet,
                        'BolumAdiNo' => intval($gorev->BolumAdiNo ?? 0),
                        'AraUrunAdiNo' => $araUrunAdiNo,
                    ]);
                }

                $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));
            }

            return ['success' => true, 'message' => 'Görev havuza iade edildi.'];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    public function startTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);
        $requestedQuantity = intval($request->input('adet', 0));

        $sonuc = DB::transaction(function () use ($id, $personelNo, $requestedQuantity) {
            $gorev = DB::table('tbPersonelGorev')->where('No', $id)->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$gorev) return ['success' => false, 'message' => 'Görev bulunamadı.', 'status' => 404];
            if (!$this->isTaskDue($gorev->GorevBaslamaTarihi ?? null)) {
                return ['success' => false, 'message' => 'Görev tarihi henüz gelmedi.', 'status' => 422];
            }

            $readyQty = max(0, intval($gorev->Adet ?? 0));
            if ($readyQty <= 0) {
                return ['success' => false, 'message' => 'Hazır adet bulunmuyor.', 'status' => 422];
            }

            $acceptQty = $requestedQuantity > 0 ? min($requestedQuantity, $readyQty) : $readyQty;
            $remaining = max(0, $readyQty - $acceptQty);

            DB::table('tbPersonelGorev')->where('No', $id)->update([
                'Onay' => 'true', 'Adet' => $acceptQty,
            ]);

            if ($remaining > 0) {
                $bomService = app(BomService::class);
                $splitTaskNo = DB::table('tbPersonelGorev')->insertGetId(array_merge([
                    'UrunIDNo' => $gorev->UrunIDNo, 'GorevBaslamaTarihi' => $gorev->GorevBaslamaTarihi,
                    'PersonelNo' => $personelNo, 'Adet' => $remaining, 'BekleyenAdet' => intval($gorev->BekleyenAdet ?? 0),
                    'Onay' => 'hazir', 'AraUrunAdiNo' => $gorev->AraUrunAdiNo, 'BolumAdiNo' => $gorev->BolumAdiNo,
                ], $bomService->buildTracePayload($bomService->traceContextFromRecord($gorev))));
            }

            $this->logTaskEvent('production_started', $gorev, null, [
                'accepted_quantity' => $acceptQty, 'remaining_quantity' => $remaining,
            ]);

            return ['success' => true, 'message' => $acceptQty . ' adet üretime alındı.', 'accepted_quantity' => $acceptQty, 'remaining_quantity' => $remaining, 'status' => 200];
        });

        return response()->json(['success' => $sonuc['success'] ?? false, 'message' => $sonuc['message']], $sonuc['status'] ?? 200);
    }

    public function startTaskGroup(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);

        $taskIds = collect($request->input('task_ids', []))->map(fn ($id) => intval($id))->filter(fn ($id) => $id > 0)->unique()->values()->all();

        if (empty($taskIds)) return response()->json(['success' => false, 'message' => 'Görev grubu bulunamadı.'], 422);

        $sonuc = DB::transaction(function () use ($taskIds, $personelNo) {
            $tasks = DB::table('tbPersonelGorev')->whereIn('No', $taskIds)->where('PersonelNo', $personelNo)->lockForUpdate()->get();
            if ($tasks->count() !== count($taskIds)) {
                return ['success' => false, 'message' => 'Görev grubundaki bazı kayıtlar bulunamadı.', 'status' => 404];
            }

            foreach ($tasks as $task) {
                if (!$this->isTaskDue($task->GorevBaslamaTarihi ?? null)) {
                    return ['success' => false, 'message' => 'Bu grupta tarihi gelmemiş görev var.', 'status' => 422];
                }
                if (intval($task->Adet ?? 0) <= 0 || !$this->isProductionReadyApproval($task->Onay ?? null)) {
                    return ['success' => false, 'message' => 'Bu grupta üretime hazır olmayan görev var.', 'status' => 422];
                }
            }

            foreach ($tasks as $task) {
                DB::table('tbPersonelGorev')->where('No', $task->No)->update(['Onay' => 'true']);
                $this->logTaskEvent('production_started', $task);
            }

            $totalStarted = $tasks->sum(fn ($t) => max(0, intval($t->Adet ?? 0)));
            return ['success' => true, 'message' => $totalStarted . ' adet üretime alındı.', 'status' => 200];
        });

        return response()->json(['success' => $sonuc['success'] ?? false, 'message' => $sonuc['message']], $sonuc['status'] ?? 200);
    }

    public function completeProduction(Request $request, $id)
    {
        $adet = intval($request->input('adet', 0));
        if ($adet <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz adet']);

        $personelNo = $this->personelNo($request);
        $sonuc = DB::transaction(function () use ($id, $personelNo, $adet) {
            $gorev = DB::table('tbPersonelGorev')->where('No', $id)->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$gorev) return ['success' => false, 'message' => 'Görev bulunamadı.'];

            $kalanAdet = max(0, intval($gorev->Adet ?? 0));
            if ($adet > $kalanAdet) $adet = $kalanAdet;
            if ($adet <= 0) return ['success' => false, 'message' => 'Tamamlanacak adet kalmadı.'];

            $yeniAdet = $kalanAdet - $adet;
            $bekleyenAdet = max(0, intval($gorev->BekleyenAdet ?? 0));

            if ($yeniAdet <= 0 && $bekleyenAdet <= 0) {
                $this->saveCompletedTask($gorev, $adet);
                DB::table('tbPersonelGorev')->where('No', $id)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $id)->update([
                    'Adet' => $yeniAdet, 'Onay' => $yeniAdet > 0 ? 'hazir' : 'false',
                ]);
            }

            $this->logTaskEvent('production_completed', $gorev, null, ['completed_quantity' => $adet]);

            return ['success' => true, 'message' => $adet . ' adet tamamlandı.'];
        });

        return response()->json($sonuc);
    }

    private function saveCompletedTask(object $gorev, int $adet): void
    {
        DB::table('tbGorevler')->insert([
            'PersonelNo' => $gorev->PersonelNo, 'UrunIDNo' => $gorev->UrunIDNo,
            'AraUrunAdiNo' => $gorev->AraUrunAdiNo, 'BolumAdiNo' => $gorev->BolumAdiNo,
            'ToplamAdet' => $adet, 'GorevBaslamaTarihi' => $gorev->GorevBaslamaTarihi,
            'GorevBitisTarihi' => now()->format('d/m/Y H:i'),
        ]);
    }
}
