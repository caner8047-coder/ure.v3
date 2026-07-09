<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApprovalHelpers;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminPersonnelController extends Controller
{
    use ApprovalHelpers;

    public function getPersonnel(Request $request)
    {
        $pendingSub = DB::table('tbPersonelGorev')
            ->selectRaw("PersonelNo, COUNT(*) as aktif_gorev")
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->openApprovalSql());
            })
            ->groupBy('PersonelNo');

        $query = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoinSub($pendingSub, 'pg', fn($j) => $j->on('p.PersonelNo', '=', 'pg.PersonelNo'))
            ->select(
                'p.PersonelNo', 'p.Ad', 'p.Soyad', 'p.Adres', 'p.Telefon', 'p.Mail', 'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw('IFNULL(pg.aktif_gorev, 0) as aktif_gorev')
            )
            ->orderByDesc('p.PersonelNo');

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('p.Ad', 'like', "%$search%")
                  ->orWhere('p.Soyad', 'like', "%$search%")
                  ->orWhere('p.Mail', 'like', "%$search%")
                  ->orWhere('p.Telefon', 'like', "%$search%")
                  ->orWhere('p.PersonelNo', 'like', "%$search%");
            });
        }

        $data = $query->get()->map(fn($u) => [
            'id'              => $u->PersonelNo,
            'name'            => $u->Ad,
            'surname'         => $u->Soyad,
            'address'         => $u->Adres,
            'phone'           => $u->Telefon,
            'email'           => $u->Mail,
            'department_id'   => $u->BolumAdiNo === null ? null : (int) $u->BolumAdiNo,
            'department_name' => $u->BolumAdi,
            'active_tasks'    => (int)$u->aktif_gorev,
        ]);

        return response()->json(['data' => $data]);
    }

    public function getIdlePersonnel()
    {
        $activePersonnelNos = DB::table('tbPersonelGorev')
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->openApprovalSql());
            })
            ->pluck('PersonelNo')
            ->unique()
            ->toArray();

        $idle = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->whereNotIn('p.PersonelNo', $activePersonnelNos)
            ->where('p.BolumAdiNo', '!=', 0)
            ->select(
                'p.PersonelNo',
                'p.Ad',
                'p.Soyad',
                'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi")
            )
            ->orderBy('p.Ad')
            ->get();

        return response()->json(['personnel' => $idle]);
    }

    public function getPersonnelProductionOverview()
    {
        if (!Schema::hasTable('tbPersonel') || !Schema::hasTable('tbPersonelGorev')) {
            return response()->json([
                'success' => true,
                'generated_at' => now()->format('d/m/Y H:i'),
                'summary' => [
                    'total_personnel' => 0, 'active_personnel' => 0, 'idle_personnel' => 0,
                    'active_task_count' => 0, 'ready_quantity' => 0, 'waiting_quantity' => 0,
                    'open_quantity' => 0, 'pool_task_count' => 0, 'pool_quantity' => 0, 'completed_quantity' => 0,
                ],
                'active_personnel' => [], 'idle_personnel' => [],
            ]);
        }

        $hasTaskSiparisSatirNo = Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo');
        $hasTaskSiparisNo = Schema::hasColumn('tbPersonelGorev', 'SiparisNo');
        $canJoinOrders = Schema::hasTable('tbSiparisSatir') && $hasTaskSiparisSatirNo;

        $activeTaskQuery = DB::table('tbPersonelGorev as pg')
            ->join('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->where('p.BolumAdiNo', '!=', 0)
            ->where(function ($query) {
                $query->where('pg.Adet', '>', 0)
                    ->orWhere('pg.BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('pg.BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->openApprovalSql('pg.Onay'));
            });

        if ($canJoinOrders) {
            $activeTaskQuery->leftJoin('tbSiparisSatir as ss', 'pg.SiparisSatirNo', '=', 'ss.No');
        }

        $activeSelect = [
            'pg.No as task_no', 'pg.PersonelNo', 'p.Ad', 'p.Soyad', 'p.BolumAdiNo',
            'pg.UrunIDNo', 'pg.AraUrunAdiNo',
            DB::raw('COALESCE(pg.Adet, 0) as Adet'), DB::raw('COALESCE(pg.BekleyenAdet, 0) as BekleyenAdet'),
            DB::raw("IFNULL(pg.GorevBaslamaTarihi, '') as GorevBaslamaTarihi"),
            DB::raw("IFNULL(pg.Onay, '') as Onay"),
            DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
            DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
            DB::raw("IFNULL(u.UrunID, '') as UrunAdi"),
            DB::raw($hasTaskSiparisSatirNo ? 'pg.SiparisSatirNo as SiparisSatirNo' : 'NULL as SiparisSatirNo'),
            DB::raw($hasTaskSiparisNo ? "IFNULL(pg.SiparisNo, '') as TaskSiparisNo" : "'' as TaskSiparisNo"),
        ];

        if ($canJoinOrders) {
            $activeSelect[] = DB::raw("IFNULL(ss.SiparisNo, '') as OrderSiparisNo");
            $activeSelect[] = DB::raw("IFNULL(ss.Musteri, '') as Musteri");
            $activeSelect[] = DB::raw("IFNULL(ss.UrunAdi, '') as SiparisUrunAdi");
            $activeSelect[] = DB::raw("IFNULL(ss.Durum, '') as SiparisDurum");
            $activeSelect[] = DB::raw("IFNULL(ss.KargoSonTeslim, '') as KargoSonTeslim");
        } else {
            $activeSelect[] = DB::raw("'' as OrderSiparisNo");
            $activeSelect[] = DB::raw("'' as Musteri");
            $activeSelect[] = DB::raw("'' as SiparisUrunAdi");
            $activeSelect[] = DB::raw("'' as SiparisDurum");
            $activeSelect[] = DB::raw("'' as KargoSonTeslim");
        }

        $activeRows = $activeTaskQuery->select($activeSelect)
            ->orderBy('p.Ad')->orderBy('p.Soyad')->orderByDesc('pg.No')->get();

        $personnelRows = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->where('p.BolumAdiNo', '!=', 0)
            ->select('p.PersonelNo', 'p.Ad', 'p.Soyad', 'p.BolumAdiNo', DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"))
            ->orderBy('p.Ad')->orderBy('p.Soyad')->get();

        $completedSummary = collect();
        $latestCompletedRows = collect();

        if (Schema::hasTable('tbGorevler')) {
            $completedSummary = DB::table('tbGorevler as gr')
                ->select('gr.PersonelNo', DB::raw('COUNT(*) as completed_count'), DB::raw('COALESCE(SUM(gr.ToplamAdet), 0) as completed_quantity'), DB::raw('MAX(gr.No) as latest_no'))
                ->whereNotNull('gr.PersonelNo')->where('gr.PersonelNo', '>', 0)->where('gr.ToplamAdet', '>', 0)
                ->groupBy('gr.PersonelNo')->get()->keyBy(fn ($row) => (int) $row->PersonelNo);

            $latestNos = $completedSummary->pluck('latest_no')->filter(fn ($no) => intval($no) > 0)->values();

            if ($latestNos->isNotEmpty()) {
                $latestCompletedRows = DB::table('tbGorevler as gr')
                    ->leftJoin('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
                    ->leftJoin('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
                    ->leftJoin('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
                    ->whereIn('gr.No', $latestNos)
                    ->select('gr.No', 'gr.PersonelNo', DB::raw('COALESCE(gr.ToplamAdet, 0) as ToplamAdet'),
                        DB::raw("IFNULL(gr.GorevBaslamaTarihi, '') as GorevBaslamaTarihi"),
                        DB::raw("IFNULL(gr.GorevBitisTarihi, '') as GorevBitisTarihi"),
                        DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                        DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                        DB::raw("IFNULL(u.UrunID, '') as UrunAdi"))
                    ->get()->keyBy(fn ($row) => (int) $row->PersonelNo);
            }
        }

        $poolSummary = collect();
        if (Schema::hasTable('tbBolumHavuz')) {
            $poolSummary = DB::table('tbBolumHavuz')
                ->select('BolumAdiNo', DB::raw('COUNT(*) as pool_tasks'), DB::raw('COALESCE(SUM(ToplamAdet), 0) as pool_quantity'), DB::raw('COALESCE(SUM(Adet), 0) as ready_pool_quantity'))
                ->groupBy('BolumAdiNo')->get()->keyBy(fn ($row) => (int) $row->BolumAdiNo);
        }

        $formatLatestCompleted = function (?object $row): ?array {
            if (!$row) return null;
            return [
                'id' => (int) $row->No, 'product_name' => trim((string) ($row->UrunAdi ?? '')),
                'component_name' => trim((string) ($row->AraUrunAdi ?? '')),
                'department_name' => trim((string) ($row->BolumAdi ?? '')),
                'quantity' => (int) ($row->ToplamAdet ?? 0),
                'started_at' => trim((string) ($row->GorevBaslamaTarihi ?? '')),
                'finished_at' => trim((string) ($row->GorevBitisTarihi ?? '')),
            ];
        };

        $activePersonnel = $activeRows->groupBy(fn ($row) => (int) $row->PersonelNo)
            ->map(function ($tasks, $personelNo) use ($completedSummary, $latestCompletedRows, $formatLatestCompleted) {
                $first = $tasks->first();
                $completed = $completedSummary->get((int) $personelNo);
                $readyQuantity = $tasks->sum(fn ($task) => max(0, (int) $task->Adet));
                $waitingQuantity = $tasks->sum(fn ($task) => max(0, (int) $task->BekleyenAdet));

                return [
                    'personnel_no' => (int) $personelNo,
                    'full_name' => trim((string) ($first->Ad ?? '') . ' ' . (string) ($first->Soyad ?? '')),
                    'department_id' => (int) ($first->BolumAdiNo ?? 0),
                    'department_name' => trim((string) ($first->BolumAdi ?? '')) ?: 'Bölüm tanımsız',
                    'summary' => [
                        'active_task_count' => $tasks->count(),
                        'ready_quantity' => (int) $readyQuantity,
                        'waiting_quantity' => (int) $waitingQuantity,
                        'open_quantity' => (int) ($readyQuantity + $waitingQuantity),
                        'completed_record_count' => (int) ($completed->completed_count ?? 0),
                        'completed_quantity' => (int) ($completed->completed_quantity ?? 0),
                    ],
                    'active_tasks' => $tasks->map(function ($task) {
                        $ready = max(0, (int) $task->Adet);
                        $waiting = max(0, (int) $task->BekleyenAdet);
                        $total = $ready + $waiting;
                        $orderNo = trim((string) ($task->TaskSiparisNo ?: $task->OrderSiparisNo ?: ''));
                        $approval = strtolower(trim((string) ($task->Onay ?? '')));
                        $statusLabel = match (true) {
                            $ready <= 0 && $waiting > 0 => 'Alt parça/stok bekliyor',
                            in_array($approval, ['hazir', 'ready'], true) && $waiting > 0 => 'Kısmi hazır, onay bekliyor',
                            in_array($approval, ['hazir', 'ready'], true) => 'Personel onayı bekliyor',
                            $waiting > 0 => 'Kısmi hazır',
                            default => 'Üretimde',
                        };

                        return [
                            'id' => (int) $task->task_no,
                            'product_name' => trim((string) ($task->UrunAdi ?: $task->SiparisUrunAdi ?: '')),
                            'component_name' => trim((string) ($task->AraUrunAdi ?? '')),
                            'department_name' => trim((string) ($task->BolumAdi ?? '')),
                            'started_at' => trim((string) ($task->GorevBaslamaTarihi ?? '')),
                            'onay' => $approval,
                            'is_in_production' => in_array($approval, ['0', 'false', 'hayir', 'no'], true),
                            'status_label' => $statusLabel,
                            'ready_quantity' => $ready, 'waiting_quantity' => $waiting, 'open_quantity' => $total,
                            'readiness_percent' => $total > 0 ? (int) round(($ready / $total) * 100) : 0,
                            'order' => [
                                'order_item_no' => (int) ($task->SiparisSatirNo ?? 0),
                                'order_no' => $orderNo,
                                'customer' => trim((string) ($task->Musteri ?? '')),
                                'line_product_name' => trim((string) ($task->SiparisUrunAdi ?? '')),
                                'status' => trim((string) ($task->SiparisDurum ?? '')),
                                'deadline' => trim((string) ($task->KargoSonTeslim ?? '')),
                            ],
                        ];
                    })->values(),
                    'latest_completed' => $formatLatestCompleted($latestCompletedRows->get((int) $personelNo)),
                ];
            })->values();

        $activePersonnelNoSet = $activeRows->pluck('PersonelNo')->map(fn ($personelNo) => (int) $personelNo)->unique()->flip();

        $idlePersonnel = $personnelRows->filter(fn ($personnel) => !$activePersonnelNoSet->has((int) $personnel->PersonelNo))
            ->map(function ($personnel) use ($completedSummary, $latestCompletedRows, $poolSummary, $formatLatestCompleted) {
                $departmentId = (int) ($personnel->BolumAdiNo ?? 0);
                $pool = $poolSummary->get($departmentId);
                $completed = $completedSummary->get((int) $personnel->PersonelNo);

                return [
                    'personnel_no' => (int) $personnel->PersonelNo,
                    'full_name' => trim((string) ($personnel->Ad ?? '') . ' ' . (string) ($personnel->Soyad ?? '')),
                    'department_id' => $departmentId,
                    'department_name' => trim((string) ($personnel->BolumAdi ?? '')) ?: 'Bölüm tanımsız',
                    'department_pool_task_count' => (int) ($pool->pool_tasks ?? 0),
                    'department_pool_quantity' => (int) ($pool->pool_quantity ?? 0),
                    'department_ready_pool_quantity' => (int) ($pool->ready_pool_quantity ?? 0),
                    'completed_record_count' => (int) ($completed->completed_count ?? 0),
                    'completed_quantity' => (int) ($completed->completed_quantity ?? 0),
                    'latest_completed' => $formatLatestCompleted($latestCompletedRows->get((int) $personnel->PersonelNo)),
                    'recommendation' => (int) ($pool->pool_tasks ?? 0) > 0
                        ? 'Bölüm havuzunda atanabilir iş var.'
                        : 'Yeni iş emri veya havuz girişi bekliyor.',
                ];
            })->values();

        $poolTotals = $poolSummary->values();

        return response()->json([
            'success' => true,
            'generated_at' => now()->format('d/m/Y H:i'),
            'summary' => [
                'total_personnel' => $personnelRows->count(),
                'active_personnel' => $activePersonnel->count(),
                'idle_personnel' => $idlePersonnel->count(),
                'active_task_count' => $activeRows->count(),
                'ready_quantity' => (int) $activeRows->sum(fn ($task) => max(0, (int) $task->Adet)),
                'waiting_quantity' => (int) $activeRows->sum(fn ($task) => max(0, (int) $task->BekleyenAdet)),
                'open_quantity' => (int) $activeRows->sum(fn ($task) => max(0, (int) $task->Adet) + max(0, (int) $task->BekleyenAdet)),
                'pool_task_count' => (int) $poolTotals->sum(fn ($pool) => (int) ($pool->pool_tasks ?? 0)),
                'pool_quantity' => (int) $poolTotals->sum(fn ($pool) => (int) ($pool->pool_quantity ?? 0)),
                'completed_quantity' => (int) $completedSummary->values()->sum(fn ($row) => (int) ($row->completed_quantity ?? 0)),
            ],
            'active_personnel' => $activePersonnel,
            'idle_personnel' => $idlePersonnel,
        ]);
    }

    public function storePersonnel(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:tbPersonel,Mail',
            'department_id' => 'required|integer|exists:tbBolum,No',
        ], [
            'department_id.required' => 'Personel için bölüm seçimi zorunludur.',
            'department_id.exists' => 'Seçilen bölüm bulunamadı.',
        ]);

        $nextPersonnelNo = (int) (DB::table('tbPersonel')->max('PersonelNo') ?? 0) + 1;

        $user = new Personnel();
        $user->PersonelNo = $nextPersonnelNo;
        $user->Ad = $request->name;
        $user->Soyad = $request->surname ?? '';
        $user->Mail = $request->email;
        $user->Telefon = $request->phone ?? '';
        $user->Adres = $request->address ?? '';
        $user->BolumAdiNo = (int) $request->input('department_id');
        $pwd = $request->filled('new_password') ? $request->new_password : '123';
        $user->Sifre = Hash::make($pwd);
        $user->save();

        return response()->json(['message' => 'Personel başarıyla eklendi', 'data' => $user]);
    }

    public function updatePersonnel(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:tbPersonel,Mail,' . $id . ',PersonelNo',
            'department_id' => 'required|integer|exists:tbBolum,No',
        ], [
            'department_id.required' => 'Personel için bölüm seçimi zorunludur.',
            'department_id.exists' => 'Seçilen bölüm bulunamadı.',
        ]);

        $user = Personnel::findOrFail($id);
        $user->Ad = $request->name;
        $user->Soyad = $request->surname ?? '';
        $user->Mail = $request->email;
        $user->Telefon = $request->phone ?? '';
        $user->Adres = $request->address ?? '';
        $user->BolumAdiNo = (int) $request->input('department_id');

        if ($request->filled('new_password')) {
            $user->Sifre = Hash::make($request->new_password);
        }

        $user->save();
        return response()->json(['message' => 'Personel güncellendi']);
    }

    public function deletePersonnel($id)
    {
        $aktifGorevSayisi = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $id)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)->orWhereRaw($this->openApprovalSql());
            })
            ->count();

        if ($aktifGorevSayisi > 0) {
            return response()->json([
                'message' => "Bu personelin {$aktifGorevSayisi} aktif görevi bulunuyor. Önce görevlerini tamamlayın veya havuza iade edin."
            ], 422);
        }

        Personnel::destroy($id);
        return response()->json(['message' => 'Personel silindi']);
    }

    public function getPersonnelWorkload()
    {
        $pendingTasks = DB::table('tbPersonelGorev')
            ->select('PersonelNo', DB::raw('COUNT(*) as aktifGorev'), DB::raw('SUM(BekleyenAdet) as bekleyenAdet'))
            ->where(function ($query) {
                $query->where('Adet', '>', 0)->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)->orWhereRaw($this->openApprovalSql());
            })
            ->groupBy('PersonelNo');

        $rows = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoinSub($pendingTasks, 'pg', fn ($join) => $join->on('p.PersonelNo', '=', 'pg.PersonelNo'))
            ->select('p.PersonelNo', 'p.Ad', 'p.Soyad', 'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw('IFNULL(pg.aktifGorev, 0) as aktifGorev'),
                DB::raw('IFNULL(pg.bekleyenAdet, 0) as bekleyenAdet'))
            ->orderBy('p.Ad')->get();

        return response()->json(['data' => $rows]);
    }
}
