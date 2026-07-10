<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsWorkOrderEvents;
use App\Http\Controllers\Concerns\SerializesRecord;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MobileTaskController extends Controller
{
    use LogsWorkOrderEvents, SerializesRecord;

    /**
     * Get list of tasks assigned to the authenticated mobile user.
     */
    public function index(Request $request)
    {
        $personelNo = $this->personelNo();
        if ($personelNo <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz personel referansı.'
            ], 400);
        }

        $query = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->where('pg.PersonelNo', $personelNo)
            ->select('pg.*', 'u.UrunID', 'au.AraUrunAdi');

        $tasks = $query->orderBy('pg.No', 'desc')->get();

        return TaskResource::collection($tasks);
    }

    /**
     * Report partial or complete production quantity.
     */
    public function submitProgress(Request $request, $id)
    {
        $request->validate([
            'completed_qty' => 'required|integer|min:1',
        ]);

        $completedQty = intval($request->input('completed_qty'));
        $personelNo = $this->personelNo();

        if ($personelNo <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz personel referansı.'], 400);
        }

        $result = DB::transaction(function () use ($id, $personelNo, $completedQty) {
            $gorev = DB::table('tbPersonelGorev')->where('No', $id)->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı.', 'status_code' => 404];
            }

            $currentQty = max(0, intval($gorev->Adet ?? 0));
            if ($completedQty > $currentQty) {
                $completedQty = $currentQty;
            }

            if ($completedQty <= 0) {
                return ['success' => false, 'message' => 'Tamamlanacak adet kalmadı.', 'status_code' => 400];
            }

            $newQty = $currentQty - $completedQty;
            $bekleyenAdet = max(0, intval($gorev->BekleyenAdet ?? 0));

            if ($newQty <= 0 && $bekleyenAdet <= 0) {
                // Task is completely finished
                $this->saveCompletedTask($gorev, $completedQty);
                DB::table('tbPersonelGorev')->where('No', $id)->delete();
            } else {
                // Update remaining quantity
                DB::table('tbPersonelGorev')->where('No', $id)->update([
                    'Adet' => $newQty,
                    'Onay' => $newQty > 0 ? 'hazir' : 'false',
                ]);
            }

            // Log production event for Event Sourcing
            $this->logTaskEvent('production_completed', $gorev, null, ['completed_quantity' => $completedQty]);

            return [
                'success' => true,
                'message' => "{$completedQty} adet başarıyla kaydedildi.",
                'completed_all' => ($newQty <= 0 && $bekleyenAdet <= 0)
            ];
        });

        if (isset($result['status_code'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['status_code']);
        }

        return response()->json($result);
    }

    /**
     * Mark task as completely finished.
     */
    public function complete(Request $request, $id)
    {
        $personelNo = $this->personelNo();

        if ($personelNo <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz personel referansı.'], 400);
        }

        $result = DB::transaction(function () use ($id, $personelNo) {
            $gorev = DB::table('tbPersonelGorev')->where('No', $id)->where('PersonelNo', $personelNo)->lockForUpdate()->first();
            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı.', 'status_code' => 404];
            }

            $remainingQty = max(0, intval($gorev->Adet ?? 0));
            if ($remainingQty <= 0) {
                return ['success' => false, 'message' => 'Görev zaten tamamlanmış.', 'status_code' => 400];
            }

            $this->saveCompletedTask($gorev, $remainingQty);
            DB::table('tbPersonelGorev')->where('No', $id)->delete();

            $this->logTaskEvent('production_completed', $gorev, null, ['completed_quantity' => $remainingQty]);

            return [
                'success' => true,
                'message' => "Görev başarıyla tamamlandı. {$remainingQty} adet üretildi."
            ];
        });

        if (isset($result['status_code'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['status_code']);
        }

        return response()->json($result);
    }

    /**
     * Resolve PersonnelNo from authenticated user.
     */
    private function personelNo(): int
    {
        $user = Auth::user();
        return intval($user->personnel_no ?? $user->PersonelNo ?? 0);
    }

    /**
     * Insert task execution into tbGorevler history.
     */
    private function saveCompletedTask(object $gorev, int $adet): void
    {
        DB::table('tbGorevler')->insert([
            'PersonelNo' => $gorev->PersonelNo,
            'UrunIDNo' => $gorev->UrunIDNo,
            'AraUrunAdiNo' => $gorev->AraUrunAdiNo,
            'BolumAdiNo' => $gorev->BolumAdiNo,
            'ToplamAdet' => $adet,
            'GorevBaslamaTarihi' => $gorev->GorevBaslamaTarihi,
            'GorevBitisTarihi' => now()->format('d/m/Y H:i'),
        ]);
    }
}
