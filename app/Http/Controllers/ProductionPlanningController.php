<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;

class ProductionPlanningController extends Controller
{
    private function isApprovedValue(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'evet', 'yes'], true);
    }

    private function serializeRecord(object|array|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true);
    }

    private function buildTaskEventBase(object $task): array
    {
        $orderItemNo = intval($task->SiparisSatirNo ?? 0);
        $orderNo = trim((string) ($task->SiparisNo ?? ''));
        $workOrderNo = null;

        if ($orderItemNo > 0 && Schema::hasTable('tbSiparisSatir')) {
            $orderRow = DB::table('tbSiparisSatir')
                ->where('No', $orderItemNo)
                ->select('SiparisNo', 'GorevNo')
                ->first();

            if ($orderRow) {
                if ($orderNo === '') {
                    $orderNo = trim((string) ($orderRow->SiparisNo ?? ''));
                }

                $resolvedWorkOrderNo = intval($orderRow->GorevNo ?? 0);
                $workOrderNo = $resolvedWorkOrderNo > 0 ? $resolvedWorkOrderNo : null;
            }
        }

        $taskNo = intval($task->No ?? 0);

        return [
            'aggregate_type' => $orderItemNo > 0 ? 'order_item' : 'personnel_task',
            'aggregate_id' => $orderItemNo > 0 ? $orderItemNo : max(1, $taskNo),
            'order_item_no' => $orderItemNo > 0 ? $orderItemNo : null,
            'order_no' => $orderNo !== '' ? $orderNo : null,
            'work_order_no' => $workOrderNo,
            'personnel_task_no' => $taskNo > 0 ? $taskNo : null,
        ];
    }

    private function logPlanningEvent(string $eventType, object $task, ?object $afterTask = null, array $attributes = []): void
    {
        app(\App\Services\WorkOrderEventLogger::class)->log(array_merge(
            $this->buildTaskEventBase($task),
            [
                'event_type' => $eventType,
                'payload_before' => $this->serializeRecord($task),
                'payload_after' => $this->serializeRecord($afterTask),
            ],
            $attributes
        ));
    }

    /**
     * Seçilen personelin görevlerini tarih bazlı getirir (Drag-Drop UI için).
     */
    public function getPersonnelTasks(Request $request, $personelNo)
    {
        $query = DB::table('tbPersonelGorev as pg')
            ->join('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->join('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.PersonelNo', $personelNo)
            ->select(
                'pg.No',
                'pg.GorevBaslamaTarihi',
                'au.AraUrunAdi',
                'au.No as AraUrunAdiNo',
                'b.BolumAdi',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.Onay',
                'au.Yol'
            )
            ->orderByRaw("STR_TO_DATE(SUBSTRING(pg.GorevBaslamaTarihi, 1, 10), '%d/%m/%Y') ASC")
            ->get();

        return response()->json(['success' => true, 'data' => $query]);
    }

    /**
     * Personel listesini getirir.
     */
    public function getPersonnelList()
    {
        $personnel = DB::table('tbPersonel')
            ->where('BolumAdiNo', '!=', 0)
            ->select('PersonelNo', DB::raw("CONCAT(Ad, ' ', Soyad) as PersonelAdi"))
            ->orderBy('Ad')
            ->get();

        return response()->json(['success' => true, 'data' => $personnel]);
    }

    /**
     * Görev adetini +1 artırır.
     */
    public function incrementTask($taskId)
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            if ($this->isApprovedValue($task->Onay ?? null)) {
                return response()->json(['success' => false, 'message' => 'Tamamlanmış görev artırılamaz.']);
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);

            $poolQuery = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($task->AraUrunAdiNo ?? 0))
                ->where('BolumAdiNo', intval($task->BolumAdiNo ?? 0))
                ->where('Adet', '>', 0)
                ->orderBy('No');
            $bomService->scopeQueryToTrace($poolQuery, $traceContext, true);
            $pool = $poolQuery->lockForUpdate()->first();

            if (!$pool) {
                return response()->json(['success' => false, 'message' => 'Bu görev için havuzda artırılabilecek adet kalmadı.']);
            }

            DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                'Adet' => intval($task->Adet ?? 0) + 1,
                'BekleyenAdet' => intval($task->BekleyenAdet ?? 0) + 1,
                'Onay' => 'false',
            ]);

            $newToplam = intval($pool->ToplamAdet ?? 0) - 1;
            $newAdet = max(0, intval($pool->Adet ?? 0) - 1);

            if ($newToplam <= 0) {
                DB::table('tbBolumHavuz')->where('No', $pool->No)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $pool->No)->update([
                    'ToplamAdet' => $newToplam,
                    'Adet' => $newAdet,
                ]);
            }

            $bomService->personelGorevTabloGuncelle(strval($task->AraUrunAdiNo));

            $updatedTask = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            $this->logPlanningEvent('planning_incremented', $task, $updatedTask, [
                'next_step_human' => 'Artirilan miktar icin personel uretim girisi yapmali.',
                'context' => [
                    'consumed_pool_no' => intval($pool->No ?? 0),
                    'pool_remaining_adet' => $newAdet,
                    'pool_remaining_toplam' => $newToplam,
                ],
            ]);

            return response()->json(['success' => true]);
        });
    }

    /**
     * Görev adetini -1 azaltır (minimum 0 olabilir).
     */
    public function decrementTask($taskId)
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            $pending = max(0, intval($task->BekleyenAdet ?? 0));
            if ($pending <= 0) {
                return response()->json(['success' => false, 'message' => 'Azaltılabilecek bekleyen adet kalmadı.']);
            }

            $newToplam = max(0, intval($task->Adet ?? 0) - 1);
            $newPending = max(0, $pending - 1);
            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);

            if ($newToplam === 0 && $newPending === 0) {
                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'Adet' => $newToplam,
                    'BekleyenAdet' => $newPending,
                    'Onay' => $newPending > 0 ? 'false' : 'true',
                ]);
            }

            $tamponDusumleri = [];
            $bomService->minAraUrunUretimiDenetle(
                intval($task->UrunIDNo ?? 0),
                '',
                strval($task->AraUrunAdiNo),
                1,
                'Planlama üzerinden görev azaltma',
                'StokHaric',
                $tamponDusumleri,
                $traceContext
            );
            $bomService->personelGorevTabloGuncelle(strval($task->AraUrunAdiNo));

            $updatedTask = $newToplam === 0 && $newPending === 0
                ? null
                : DB::table('tbPersonelGorev')->where('No', $taskId)->first();

            $this->logPlanningEvent('planning_decremented', $task, $updatedTask, [
                'next_step_human' => $newPending > 0
                    ? 'Kalan miktar icin gorev devam ediyor.'
                    : 'Gorev sifirlandi; gerekiyorsa havuzdan yeniden planlanabilir.',
                'context' => [
                    'new_total_amount' => $newToplam,
                    'new_pending_amount' => $newPending,
                ],
            ]);

            return response()->json(['success' => true]);
        });
    }

    /**
     * Görevi siler, adetleri havuza geri yükler ve stok/tampon güncellemelerini tetikler.
     */
    public function deleteTask($taskId)
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            $araUrunAdiNo = $task->AraUrunAdiNo;
            $bekleyenAdet = max(0, intval($task->BekleyenAdet ?? 0));

            // Görevi sil
            DB::table('tbPersonelGorev')->where('No', $taskId)->delete();

            $bomService = app(BomService::class);
            if ($bekleyenAdet > 0) {
                $tamponDusumleri = [];
                $bomService->minAraUrunUretimiDenetle(
                    intval($task->UrunIDNo ?? 0),
                    '',
                    strval($araUrunAdiNo),
                    $bekleyenAdet,
                    'Personel üzerinden alınan görev',
                    'StokHaric',
                    $tamponDusumleri,
                    $bomService->traceContextFromRecord($task)
                );
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            $this->logPlanningEvent('personnel_task_deleted', $task, null, [
                'next_step_human' => $bekleyenAdet > 0
                    ? 'Bekleyen miktar havuza dondu; yeniden planlama yapilabilir.'
                    : 'Tamamlanan kisim stokta birakildi.',
                'context' => [
                    'returned_amount' => $bekleyenAdet,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => $bekleyenAdet > 0
                    ? "{$bekleyenAdet} adet havuza geri aktarıldı."
                    : 'Görev silindi.',
            ]);
        });
    }

    /**
     * Görevin tarihini değiştirir (Drag-Drop update).
     */
    public function updateTaskDate(Request $request, $taskId)
    {
        $newDate = $request->input('date'); // Beklenen format YYYY-MM-DD
        if (!$newDate) {
            return response()->json(['success' => false, 'message' => 'Tarih gerekli.']);
        }

        // Formatı DD/MM/YYYY hh:mm yap
        $formattedDate = \Carbon\Carbon::parse($newDate)->format('d/m/Y');

        return DB::transaction(function () use ($taskId, $formattedDate) {
            $mevcutGorev = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            if (!$mevcutGorev) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            $araUrunAdiNo = intval($mevcutGorev->AraUrunAdiNo ?? 0);
            $bekleyenAdet = max(0, intval($mevcutGorev->BekleyenAdet ?? 0));
            $tamamlananAdet = max(0, intval($mevcutGorev->Adet ?? 0) - $bekleyenAdet);
            if ($tamamlananAdet > 0) {
                return response()->json(['success' => false, 'message' => 'Kısmen tamamlanmış görevlerin tarihi değiştirilemez.']);
            }

            $tasinacakAdet = $bekleyenAdet > 0 ? $bekleyenAdet : intval($mevcutGorev->Adet ?? 0);
            if ($tasinacakAdet <= 0) {
                return response()->json(['success' => false, 'message' => 'Taşınacak aktif adet bulunamadı.']);
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($mevcutGorev);
            
            // Aynı tarihte aynı parça için başka görev var mı?
            $mevcutKayitQuery = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $mevcutGorev->PersonelNo)
                ->whereRaw("SUBSTRING(GorevBaslamaTarihi, 1, 10) = ?", [$formattedDate])
                ->where('No', '!=', $taskId);

            $bomService->scopeQueryToTrace($mevcutKayitQuery, $traceContext, true);
            $mevcutKayit = $mevcutKayitQuery->first();

            if ($mevcutKayit) {
                $mevcutBekleyen = max(0, intval($mevcutKayit->BekleyenAdet ?? 0));
                $mevcutTamamlanan = max(0, intval($mevcutKayit->Adet ?? 0) - $mevcutBekleyen);
                if ($mevcutTamamlanan > 0) {
                    return response()->json(['success' => false, 'message' => 'Hedef tarihte kısmen tamamlanmış görev bulunduğu için birleştirme yapılamaz.']);
                }

                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'Adet' => intval($mevcutKayit->Adet ?? 0) + $tasinacakAdet,
                    'BekleyenAdet' => $mevcutBekleyen + $tasinacakAdet,
                    'Onay' => 'false',
                ]);

                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                $yeniTarih = $formattedDate . ' ' . now()->format('H:i');
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'GorevBaslamaTarihi' => $yeniTarih
                ]);
            }

            $updatedTask = DB::table('tbPersonelGorev')
                ->where('No', $mevcutKayit?->No ?? $taskId)
                ->first();

            $this->logPlanningEvent('planning_rescheduled', $mevcutGorev, $updatedTask, [
                'next_step_human' => 'Yeni tarihe gore uretim takibi devam etmeli.',
                'context' => [
                    'target_date' => $formattedDate,
                    'merged_into_task_no' => intval($mevcutKayit->No ?? 0) > 0 ? intval($mevcutKayit->No) : null,
                ],
            ]);

            return response()->json(['success' => true]);
        });
    }
}
