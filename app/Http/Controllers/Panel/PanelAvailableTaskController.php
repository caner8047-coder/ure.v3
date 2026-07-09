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

class PanelAvailableTaskController extends Controller
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
        return "STR_TO_DATE(IFNULL({$column}, '01/01/2000'), '%d/%m/%Y %H:%i')";
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
            $component = DB::table('tbAraUrun')->where('No', $componentNo)->first();
            if ($component && !empty($component->Resim)) {
                $task->Resim = $component->Resim;
            }
        }
        return $task;
    }

    public function availableTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $tasks = DB::select("
            SELECT MAX(bh.No) AS No, bh.AraUrunAdiNo,
                   CASE WHEN IFNULL(au.Yol, '') = '' THEN SUM(COALESCE(bh.Adet, 0)) ELSE MAX(COALESCE(bh.Adet, 0)) END AS Adet,
                   SUM(COALESCE(bh.ToplamAdet, 0)) AS ToplamAdet,
                   CONCAT(IFNULL(MAX(bh.GorevBaslangicTarihi), ''), CASE WHEN IFNULL(MAX(bh.GorevBaslangicSaati), '') <> '' THEN CONCAT(' ', MAX(bh.GorevBaslangicSaati)) ELSE '' END) AS GorevBaslangicTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi, IFNULL(MAX(b.BolumAdi),'') AS BolumAdi, IFNULL(MAX(u.UrunID),'') AS UrunAdi,
                   COALESCE(MAX({$componentImageSql}), MAX({$productImageSql}), '') AS Resim,
                   COALESCE(MAX({$componentTypeSql}), 'Ara Mamül') AS UrunCesidi,
                   IFNULL(MAX(bh.Aciklama), '') AS Aciklama
            FROM tbBolumHavuz bh
            LEFT JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON bh.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON bh.UrunIDNo = u.No
            WHERE bh.Adet > 0 AND bh.BolumAdiNo = ?
            GROUP BY bh.AraUrunAdiNo, au.AraUrunAdi, au.Yol
            ORDER BY au.AraUrunAdi ASC
        ", [$bolumAdiNo]);

        return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($tasks)]);
    }

    public function takeTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $adet = intval($request->input('adet', 0));
        $sonuc = DB::transaction(function () use ($personelNo, $adet, $id) {
            $bomService = app(BomService::class);
            $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$personel) return ['success' => false, 'message' => 'Personel bulunamadı'];

            $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
            if (!$havuz) return ['success' => false, 'message' => 'Havuz kaydı bulunamadı'];

            if (intval($personel->BolumAdiNo ?? 0) !== intval($havuz->BolumAdiNo ?? 0)) {
                return ['success' => false, 'message' => 'Farklı bölüm görevini alamazsınız.'];
            }

            if ($bomService->hasOpenDescendantWork(strval($havuz->AraUrunAdiNo ?? 0), $bomService->traceContextFromRecord($havuz))) {
                return ['success' => false, 'message' => 'Alt bileşen görevleri tamamlanmadan bu görev alınamaz.'];
            }

            $havuzKayitlari = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($havuz->AraUrunAdiNo ?? 0))
                ->where('BolumAdiNo', intval($havuz->BolumAdiNo ?? 0))
                ->where('Adet', '>', 0)
                ->orderByRaw($this->legacyDateOrderSql('GorevBaslangicTarihi') . ' ASC')
                ->orderBy('No')->lockForUpdate()->get();

            $havuzToplam = intval($havuzKayitlari->sum(fn ($row) => intval($row->Adet ?? 0)));
            $alinacakAdet = $adet > 0 ? min($adet, $havuzToplam) : $havuzToplam;
            if ($alinacakAdet <= 0) return ['success' => false, 'message' => 'Alınacak görev adedi kalmamış.'];

            $kalanMiktar = $alinacakAdet;
            foreach ($havuzKayitlari as $kayit) {
                if ($kalanMiktar <= 0) break;
                $kayitAdet = intval($kayit->Adet ?? 0);
                $dusulecek = min($kayitAdet, $kalanMiktar);
                $yeniAdet = max(0, $kayitAdet - $dusulecek);
                $yeniToplam = max(0, intval($kayit->ToplamAdet ?? 0) - $dusulecek);
                if ($yeniToplam <= 0) DB::table('tbBolumHavuz')->where('No', $kayit->No)->delete();
                else DB::table('tbBolumHavuz')->where('No', $kayit->No)->update(['Adet' => $yeniAdet, 'ToplamAdet' => $yeniToplam]);
                $kalanMiktar -= $dusulecek;
            }

            $taskId = DB::table('tbPersonelGorev')->insertGetId(array_merge([
                'UrunIDNo' => $havuz->UrunIDNo, 'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'PersonelNo' => $personelNo, 'Adet' => $alinacakAdet, 'BekleyenAdet' => 0,
                'Onay' => 'false', 'AraUrunAdiNo' => $havuz->AraUrunAdiNo, 'BolumAdiNo' => $havuz->BolumAdiNo,
            ], $bomService->buildTracePayload($bomService->traceContextFromRecord($havuz))));

            $createdTask = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            $this->logTaskEvent('personnel_task_taken', $havuz, $createdTask, [
                'aggregate_type' => 'personnel_task', 'aggregate_id' => intval($createdTask->No ?? $taskId),
                'personnel_task_no' => intval($createdTask->No ?? $taskId),
                'status_before' => 'HavuzdaBekliyor', 'status_after' => 'Personelde',
                'payload_before' => $this->serializeRecord($havuz), 'payload_after' => $this->serializeRecord($createdTask),
            ]);

            return ['success' => true, 'message' => $alinacakAdet . ' adet görev alındı.'];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }
}
