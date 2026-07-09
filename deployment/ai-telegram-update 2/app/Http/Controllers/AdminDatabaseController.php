<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Personnel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;
use App\Services\ProductMergeService;
use App\Services\StockMovementLogger;

class AdminDatabaseController extends Controller
{
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

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi admin islemini bozmasin.
        }
    }

    private function openApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function productionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$normalized} IN ('hazir', 'ready'))";
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

    // ============================================
    // PRODUCT SETTINGS API (UrunOzellikleriAyarlari)
    // ============================================
    public function getProductSettingsLookups()
    {
        $urunler = DB::table('tbUrunler')
            ->whereNotIn('No', [10, 49, 50])
            ->select('No', 'UrunID')
            ->orderBy('UrunID')
            ->get();

        return response()->json(['success' => true, 'urunler' => $urunler]);
    }

    public function getProductSettingsDetails($urunNo)
    {
        $urun = DB::table('tbUrunler')->where('No', $urunNo)->first();
        if (!$urun) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.']);
        }

        $tablo = [];
        if (!empty($urun->AraAdlarYol)) {
            $parcalar = explode(':', $urun->AraAdlarYol);
            $seenAraUrun = [];
            foreach ($parcalar as $p) {
                if (trim($p) == '') continue;
                $parts = explode('-', $p);
                if (count($parts) >= 2) {
                    $araNo = intval($parts[0]);
                    $adet = intval($parts[1]);

                    if (!in_array($araNo, $seenAraUrun)) {
                        $araUrunData = DB::table('tbAraUrun')->where('No', $araNo)->first();
                        if ($araUrunData) {
                            $tablo[] = [
                                'No' => $urunNo,
                                'UrunID' => $urun->UrunID,
                                'SistemAdi' => $urun->SistemAdi,
                                'SistemKodu' => $urun->SistemKodu,
                                'AraUrunNo' => $araNo,
                                'AraUrun' => $araUrunData->AraUrunAdi,
                                'Adet' => $adet,
                                'Performans' => $araUrunData->Performans
                            ];
                            $seenAraUrun[] = $araNo;
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'sistemAdi' => $urun->SistemAdi,
            'sistemKodu' => $urun->SistemKodu,
            'tablo' => $tablo
        ]);
    }

    public function updateProductSettings(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            $urunNo = $request->input('urunNo');
            $sistemAdi = $request->input('sistemAdi');
            $sistemKodu = $request->input('sistemKodu');

            DB::transaction(function () use ($updates, $urunNo, $sistemAdi, $sistemKodu) {
                foreach ($updates as $upd) {
                    if (isset($upd['AraUrunNo']) && isset($upd['Performans'])) {
                        DB::table('tbAraUrun')
                            ->where('No', $upd['AraUrunNo'])
                            ->update(['Performans' => $upd['Performans']]);
                    }
                }

                if ($urunNo) {
                    DB::table('tbUrunler')
                        ->where('No', $urunNo)
                        ->update([
                            'SistemAdi' => empty($sistemAdi) ? null : $sistemAdi,
                            'SistemKodu' => empty($sistemKodu) ? null : $sistemKodu
                        ]);
                }
            });

            return response()->json(['success' => true, 'status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // PERSONNEL (tbPersonel)
    // ==========================================
    public function getPersonnel(Request $request)
    {
        // Aktif görev sayısı alt sorgusu
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

    /**
     * Görevsiz personelleri getir (PersonelGorevsiz.aspx karşılığı)
     * Üzerinde aktif (tamamlanmamış) görev olmayan personelleri döndürür.
     */
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
                    'total_personnel' => 0,
                    'active_personnel' => 0,
                    'idle_personnel' => 0,
                    'active_task_count' => 0,
                    'ready_quantity' => 0,
                    'waiting_quantity' => 0,
                    'open_quantity' => 0,
                    'pool_task_count' => 0,
                    'pool_quantity' => 0,
                    'completed_quantity' => 0,
                ],
                'active_personnel' => [],
                'idle_personnel' => [],
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
            'pg.No as task_no',
            'pg.PersonelNo',
            'p.Ad',
            'p.Soyad',
            'p.BolumAdiNo',
            'pg.UrunIDNo',
            'pg.AraUrunAdiNo',
            DB::raw('COALESCE(pg.Adet, 0) as Adet'),
            DB::raw('COALESCE(pg.BekleyenAdet, 0) as BekleyenAdet'),
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

        $activeRows = $activeTaskQuery
            ->select($activeSelect)
            ->orderBy('p.Ad')
            ->orderBy('p.Soyad')
            ->orderByDesc('pg.No')
            ->get();

        $personnelRows = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->where('p.BolumAdiNo', '!=', 0)
            ->select(
                'p.PersonelNo',
                'p.Ad',
                'p.Soyad',
                'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi")
            )
            ->orderBy('p.Ad')
            ->orderBy('p.Soyad')
            ->get();

        $completedSummary = collect();
        $latestCompletedRows = collect();

        if (Schema::hasTable('tbGorevler')) {
            $completedSummary = DB::table('tbGorevler as gr')
                ->select(
                    'gr.PersonelNo',
                    DB::raw('COUNT(*) as completed_count'),
                    DB::raw('COALESCE(SUM(gr.ToplamAdet), 0) as completed_quantity'),
                    DB::raw('MAX(gr.No) as latest_no')
                )
                ->whereNotNull('gr.PersonelNo')
                ->where('gr.PersonelNo', '>', 0)
                ->where('gr.ToplamAdet', '>', 0)
                ->groupBy('gr.PersonelNo')
                ->get()
                ->keyBy(fn ($row) => (int) $row->PersonelNo);

            $latestNos = $completedSummary
                ->pluck('latest_no')
                ->filter(fn ($no) => intval($no) > 0)
                ->values();

            if ($latestNos->isNotEmpty()) {
                $latestCompletedRows = DB::table('tbGorevler as gr')
                    ->leftJoin('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
                    ->leftJoin('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
                    ->leftJoin('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
                    ->whereIn('gr.No', $latestNos)
                    ->select(
                        'gr.No',
                        'gr.PersonelNo',
                        DB::raw('COALESCE(gr.ToplamAdet, 0) as ToplamAdet'),
                        DB::raw("IFNULL(gr.GorevBaslamaTarihi, '') as GorevBaslamaTarihi"),
                        DB::raw("IFNULL(gr.GorevBitisTarihi, '') as GorevBitisTarihi"),
                        DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                        DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                        DB::raw("IFNULL(u.UrunID, '') as UrunAdi")
                    )
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->PersonelNo);
            }
        }

        $poolSummary = collect();
        if (Schema::hasTable('tbBolumHavuz')) {
            $poolSummary = DB::table('tbBolumHavuz')
                ->select(
                    'BolumAdiNo',
                    DB::raw('COUNT(*) as pool_tasks'),
                    DB::raw('COALESCE(SUM(ToplamAdet), 0) as pool_quantity'),
                    DB::raw('COALESCE(SUM(Adet), 0) as ready_pool_quantity')
                )
                ->groupBy('BolumAdiNo')
                ->get()
                ->keyBy(fn ($row) => (int) $row->BolumAdiNo);
        }

        $formatLatestCompleted = function (?object $row): ?array {
            if (!$row) {
                return null;
            }

            return [
                'id' => (int) $row->No,
                'product_name' => trim((string) ($row->UrunAdi ?? '')),
                'component_name' => trim((string) ($row->AraUrunAdi ?? '')),
                'department_name' => trim((string) ($row->BolumAdi ?? '')),
                'quantity' => (int) ($row->ToplamAdet ?? 0),
                'started_at' => trim((string) ($row->GorevBaslamaTarihi ?? '')),
                'finished_at' => trim((string) ($row->GorevBitisTarihi ?? '')),
            ];
        };

        $activePersonnel = $activeRows
            ->groupBy(fn ($row) => (int) $row->PersonelNo)
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
                            'ready_quantity' => $ready,
                            'waiting_quantity' => $waiting,
                            'open_quantity' => $total,
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
            })
            ->values();

        $activePersonnelNoSet = $activeRows
            ->pluck('PersonelNo')
            ->map(fn ($personelNo) => (int) $personelNo)
            ->unique()
            ->flip();

        $idlePersonnel = $personnelRows
            ->filter(fn ($personnel) => !$activePersonnelNoSet->has((int) $personnel->PersonelNo))
            ->map(function ($personnel) use ($completedSummary, $latestCompletedRows, $poolSummary, $formatLatestCompleted) {
                $departmentId = (int) ($personnel->BolumAdiNo ?? 0);
                $pool = $poolSummary->get($departmentId);
                $completed = $completedSummary->get((int) $personnel->PersonelNo);
                $poolTasks = (int) ($pool->pool_tasks ?? 0);
                $poolQuantity = (int) ($pool->pool_quantity ?? 0);

                return [
                    'personnel_no' => (int) $personnel->PersonelNo,
                    'full_name' => trim((string) ($personnel->Ad ?? '') . ' ' . (string) ($personnel->Soyad ?? '')),
                    'department_id' => $departmentId,
                    'department_name' => trim((string) ($personnel->BolumAdi ?? '')) ?: 'Bölüm tanımsız',
                    'department_pool_task_count' => $poolTasks,
                    'department_pool_quantity' => $poolQuantity,
                    'department_ready_pool_quantity' => (int) ($pool->ready_pool_quantity ?? 0),
                    'completed_record_count' => (int) ($completed->completed_count ?? 0),
                    'completed_quantity' => (int) ($completed->completed_quantity ?? 0),
                    'latest_completed' => $formatLatestCompleted($latestCompletedRows->get((int) $personnel->PersonelNo)),
                    'recommendation' => $poolTasks > 0
                        ? 'Bölüm havuzunda atanabilir iş var.'
                        : 'Yeni iş emri veya havuz girişi bekliyor.',
                ];
            })
            ->values();

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

        // bcrypt ile güvenli şifre hashleme (AuthController passwordMatches() her ikisini de destekler)
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

        // Şifre değişikliği — bcrypt kullan (AuthController her iki formatı destekler)
        if ($request->filled('new_password')) {
            $user->Sifre = Hash::make($request->new_password);
        }

        $user->save();

        return response()->json(['message' => 'Personel güncellendi']);
    }

    public function deletePersonnel($id)
    {
        // Aktif görevi olan personel silinemesin
        $aktifGorevSayisi = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $id)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->openApprovalSql());
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
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->openApprovalSql());
            })
            ->groupBy('PersonelNo');

        $rows = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoinSub($pendingTasks, 'pg', function ($join) {
                $join->on('p.PersonelNo', '=', 'pg.PersonelNo');
            })
            ->select(
                'p.PersonelNo',
                'p.Ad',
                'p.Soyad',
                'p.BolumAdiNo',
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw('IFNULL(pg.aktifGorev, 0) as aktifGorev'),
                DB::raw('IFNULL(pg.bekleyenAdet, 0) as bekleyenAdet')
            )
            ->orderBy('p.Ad')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function getPoolTasks(Request $request)
    {
        $query = DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'bh.UrunIDNo', '=', 'u.No')
            ->select(
                'bh.No',
                'bh.UrunIDNo',
                'bh.AraUrunAdiNo',
                'bh.Adet',
                'bh.ToplamAdet',
                'bh.GorevBaslangicTarihi',
                'bh.GorevBaslangicSaati',
                'bh.Aciklama',
                'bh.BolumAdiNo',
                'bh.SiparisSatirNo',
                DB::raw("IFNULL(a.Yol, '') as component_path"),
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name"),
                DB::raw("IFNULL(u.UrunID, '') as product_name")
            );

        if ($request->filled('department_id')) {
            $query->where('bh.BolumAdiNo', intval($request->department_id));
        }

        $rows = $query
            ->orderBy('bh.GorevBaslangicTarihi')
            ->orderBy('bh.GorevBaslangicSaati')
            ->get();

        $reservedByOrderAndComponent = $this->reservedStockMapForPoolRows($rows);
        $bomService = app(BomService::class);

        $rows = $rows->map(function ($row) use ($reservedByOrderAndComponent, $bomService) {
                $reservedFromStock = (int) ($reservedByOrderAndComponent[(int) ($row->SiparisSatirNo ?? 0)][(int) ($row->AraUrunAdiNo ?? 0)] ?? 0);
                $directChildReservedFromStock = $this->directChildReservedStockTotal($row, $reservedByOrderAndComponent);
                $hasOpenDescendantWork = $bomService->hasOpenDescendantWork(
                    strval($row->AraUrunAdiNo ?? 0),
                    $bomService->traceContextFromRecord($row)
                );
                $assignableQuantity = $bomService->effectivePoolAssignableQuantity($row);
                $netProduction = (int) $row->ToplamAdet;
                $bomRequired = $netProduction + $reservedFromStock;

                return [
                    'id' => $row->No,
                    'urun_id_no' => $row->UrunIDNo,
                    'ara_urun_no' => $row->AraUrunAdiNo,
                    'adet' => $assignableQuantity,
                    'toplam_adet' => $netProduction,
                    'atanabilir_adet' => $assignableQuantity,
                    'planlanabilir_adet' => $netProduction,
                    'uretilecek_net_adet' => $netProduction,
                    'stoktan_ayrilan_adet' => $reservedFromStock,
                    'alt_stoktan_ayrilan_adet' => $directChildReservedFromStock,
                    'alt_gorev_bekliyor' => $hasOpenDescendantWork,
                    'bom_ihtiyac_adet' => $bomRequired,
                    'requested_adet' => $bomRequired,
                    'gorev_tarihi' => trim((string) ($row->GorevBaslangicTarihi ?? '')),
                    'gorev_saati' => trim((string) ($row->GorevBaslangicSaati ?? '')),
                    'aciklama' => trim((string) ($row->Aciklama ?? '')),
                    'department_id' => $row->BolumAdiNo,
                    'department_name' => $row->department_name,
                    'component_name' => $row->component_name,
                    'component_has_children' => trim((string) ($row->component_path ?? '')) !== '',
                    'product_name' => $row->product_name,
                    'siparis_satir_no' => $row->SiparisSatirNo,
                ];
            });

        return response()->json(['data' => $rows]);
    }

    private function reservedStockMapForPoolRows($rows): array
    {
        if (!Schema::hasColumn('tbSiparisSatir', 'TamponDusumleri')) {
            return [];
        }

        $orderItemNos = $rows
            ->pluck('SiparisSatirNo')
            ->map(fn ($no) => (int) $no)
            ->filter(fn ($no) => $no > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($orderItemNos)) {
            return [];
        }

        $tamponRows = DB::table('tbSiparisSatir')
            ->whereIn('No', $orderItemNos)
            ->pluck('TamponDusumleri', 'No');

        $map = [];
        foreach ($tamponRows as $satirNo => $json) {
            $decoded = json_decode((string) $json, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $componentNo = (int) ($entry['araNo'] ?? $entry['AraUrunAdiNo'] ?? $entry['ara_urun_no'] ?? 0);
                $quantity = (int) ($entry['adet'] ?? $entry['Adet'] ?? $entry['quantity'] ?? 0);
                if ($componentNo <= 0 || $quantity <= 0) {
                    continue;
                }

                $satirKey = (int) $satirNo;
                $map[$satirKey][$componentNo] = (int) (($map[$satirKey][$componentNo] ?? 0) + $quantity);
            }
        }

        return $map;
    }

    private function directChildReservedStockTotal(object $poolRow, array $reservedByOrderAndComponent): int
    {
        $satirNo = (int) ($poolRow->SiparisSatirNo ?? 0);
        if ($satirNo <= 0 || empty($reservedByOrderAndComponent[$satirNo])) {
            return 0;
        }

        $reservedForOrder = $reservedByOrderAndComponent[$satirNo];
        $total = 0;
        foreach ($this->directChildComponentNos((string) ($poolRow->component_path ?? '')) as $childNo) {
            $total += (int) ($reservedForOrder[$childNo] ?? 0);
        }

        return $total;
    }

    private function directChildComponentNos(string $path): array
    {
        $path = trim($path);
        if ($path === '') {
            return [];
        }

        $children = [];
        foreach (explode(':', $path) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $childNo = (int) ($parts[0] ?? 0);
            if ($childNo > 0) {
                $children[$childNo] = true;
            }
        }

        return array_keys($children);
    }

    public function assignPoolTask(Request $request, $id)
    {
        $request->validate([
            'personel_no' => 'required|integer',
            'adet' => 'nullable|integer|min:1',
            'gorev_tarihi' => 'nullable|date_format:Y-m-d',
        ]);

        $taskDate = $request->filled('gorev_tarihi')
            ? \Carbon\Carbon::createFromFormat('Y-m-d', (string) $request->input('gorev_tarihi'))->startOfDay()
            : now()->startOfDay();

        if ($taskDate->lt(now()->startOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Görev tarihi bugünden önce olamaz.',
            ], 422);
        }

        $legacyTaskDate = $taskDate->format('d/m/Y');
        $legacyTaskDateTime = $legacyTaskDate . ' 00:00';

        $sonuc = DB::transaction(function () use ($id, $request, $legacyTaskDate, $legacyTaskDateTime) {
            $havuz = DB::table('tbBolumHavuz')
                ->where('No', $id)
                ->lockForUpdate()
                ->first();

            if (!$havuz || intval($havuz->ToplamAdet) <= 0) {
                return ['success' => false, 'message' => 'Havuz görevi bulunamadı ya da tamamlanmış.', 'status' => 404];
            }

            $personel = DB::table('tbPersonel')
                ->where('PersonelNo', intval($request->personel_no))
                ->lockForUpdate()
                ->first();

            if (!$personel) {
                return ['success' => false, 'message' => 'Personel bulunamadı.', 'status' => 404];
            }

            $havuzBolumAdiNo = intval($havuz->BolumAdiNo ?? 0);
            $personelBolumAdiNo = intval($personel->BolumAdiNo ?? 0);
            if ($havuzBolumAdiNo > 0 && $personelBolumAdiNo !== $havuzBolumAdiNo) {
                return [
                    'success' => false,
                    'message' => 'Bu görev yalnızca aynı bölümdeki personele atanabilir.',
                    'status' => 422,
                ];
            }

            $yeniAdet = intval($request->adet ?? $havuz->ToplamAdet);
            $toplamAdet = intval($havuz->ToplamAdet);
            $mevcutAdet = intval($havuz->Adet);
            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($havuz);
            $atanabilirAdet = $bomService->effectivePoolAssignableQuantity($havuz, $mevcutAdet);

            if ($yeniAdet <= 0 || $yeniAdet > $toplamAdet) {
                return ['success' => false, 'message' => 'Girdiğiniz değer kalan görev adedinden fazla olamaz.', 'status' => 422];
            }

            $hazirAtanacakAdet = min($yeniAdet, $atanabilirAdet);
            $bekleyenAtanacakAdet = max(0, $yeniAdet - $hazirAtanacakAdet);

            // PersonelGorev tablosuna ekle/güncelle (ASP.NET PersonelGorevTablosunaEkle mantığı)
            $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
            $personelNo = intval($personel->PersonelNo);
            $tarih = $legacyTaskDateTime;

            $mevcutKayitQuery = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $personelNo)
                ->whereRaw($this->productionReadyApprovalSql())
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->whereRaw('SUBSTR(GorevBaslamaTarihi, 1, 10) = ?', [$legacyTaskDate]);

            $bomService->scopeQueryToTrace($mevcutKayitQuery, $traceContext, true);
            $mevcutKayit = $mevcutKayitQuery->first();

            $assignedTaskNo = null;

            if ($mevcutKayit) {
                $mevcutToplam = max(0, intval($mevcutKayit->Adet ?? 0))
                    + max(0, intval($mevcutKayit->BekleyenAdet ?? 0))
                    + $yeniAdet;
                $split = $bomService->personnelTaskReadySplit($mevcutKayit, $mevcutToplam);

                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'Adet' => intval($split['ready']),
                    'BekleyenAdet' => intval($split['waiting']),
                    'Onay' => 'hazir',
                ]);

                $assignedTaskNo = intval($mevcutKayit->No ?? 0);
            } else {
                $assignedTaskNo = DB::table('tbPersonelGorev')->insertGetId(array_merge([
                    'UrunIDNo' => intval($havuz->UrunIDNo ?? 0),
                    'GorevBaslamaTarihi' => $tarih,
                    'PersonelNo' => $personelNo,
                    'Adet' => $hazirAtanacakAdet,
                    'BekleyenAdet' => $bekleyenAtanacakAdet,
                    'Onay' => 'hazir',
                    'AraUrunAdiNo' => $araUrunAdiNo,
                    'BolumAdiNo' => $havuz->BolumAdiNo,
                ], $bomService->buildTracePayload($traceContext)));
            }

            // Havuz güncelle (ASP.NET mantığı: ToplamAdet -= yeniAdet, Adet -= min(yeniAdet,max))
            $newToplamAdet = $toplamAdet - $yeniAdet;
            $newAdet = max(0, $mevcutAdet - $hazirAtanacakAdet);

            if ($newToplamAdet <= 0) {
                // Havuzdan sil (ASP.NET davranışı)
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $havuz->No)->update([
                    'ToplamAdet' => $newToplamAdet,
                    'Adet' => $newAdet,
                ]);
            }

            // personelGorevTabloGuncelle — görev atama sonrası senkronizasyon
            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            if ($assignedTaskNo > 0) {
                $assignedTask = DB::table('tbPersonelGorev')->where('No', $assignedTaskNo)->first();
                if ($assignedTask) {
                    app(\App\Services\WorkOrderEventLogger::class)->log(array_merge(
                        $this->buildTaskEventBase($assignedTask),
                        [
                            'event_type' => 'task_assigned_by_admin',
                            'next_step_human' => 'Atanan personelin hazir gorevi onaylayip uretime gecmesi bekleniyor.',
                            'payload_before' => $this->serializeRecord($havuz),
                            'payload_after' => $this->serializeRecord($assignedTask),
                            'context' => [
                                'assigned_amount' => $yeniAdet,
                                'ready_amount' => $hazirAtanacakAdet,
                                'waiting_amount' => $bekleyenAtanacakAdet,
                                'scheduled_date' => $legacyTaskDate,
                                'pool_no' => intval($havuz->No ?? 0),
                                'personnel_no' => $personelNo,
                            ],
                        ]
                    ));
                }
            }

            $message = $yeniAdet . ' adet görev personele atandı.';
            if ($bekleyenAtanacakAdet > 0) {
                $message .= ' ' . $bekleyenAtanacakAdet . ' adet alt görev/stok tamamlanınca açılacak.';
            }

            return ['success' => true, 'message' => $message, 'status' => 200];
        });

        return response()->json(
            ['success' => $sonuc['success'] ?? false, 'message' => $sonuc['message']],
            $sonuc['status'] ?? (($sonuc['success'] ?? false) ? 200 : 422)
        );
    }

    // ==========================================
    // HAVUZ — SATIR SİLME + TAMPON STOK İADE
    // ==========================================
    public function deletePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) {
                    return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];
                }

                $araUrunAdiNo = intval($havuz->AraUrunAdiNo);
                $toplamAdet = intval($havuz->ToplamAdet);

                // ASP.NET tamponStokKontrol: alt parçaların tamponlarını geri iade et
                $bomService = app(BomService::class);
                $bomService->tamponStokKontrol(strval($araUrunAdiNo), $toplamAdet);

                DB::table('tbBolumHavuz')->where('No', $id)->delete();

                // personelGorevTabloGuncelle — havuz değişimi sonrası senkronizasyon
                $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

                // ─── Hayalet sipariş önleme: Bağlı siparişi kontrol et ───
                $sipSatirNo = intval($havuz->SiparisSatirNo ?? 0);
                if ($sipSatirNo > 0) {
                    $kalanHavuz = DB::table('tbBolumHavuz')
                        ->where('SiparisSatirNo', $sipSatirNo)->count();
                    $kalanGorev = DB::table('tbPersonelGorev')
                        ->where('SiparisSatirNo', $sipSatirNo)
                        ->where(function ($q) {
                            $q->where('Adet', '>', 0)
                              ->orWhere('BekleyenAdet', '>', 0);
                        })
                        ->where(function ($q) {
                            $q->where('BekleyenAdet', '>', 0)
                              ->orWhereRaw($this->openApprovalSql());
                        })->count();

                    if ($kalanHavuz <= 0 && $kalanGorev <= 0) {
                        DB::table('tbSiparisSatir')
                            ->where('No', $sipSatirNo)
                            ->where('Durum', 'IsEmriVerildi')
                            ->update([
                                'Durum' => 'UretimBekliyor',
                                'GorevNo' => null,
                                'IsEmriTarihi' => null,
                                'GuncellemeTarihi' => now(),
                            ]);
                    }
                }

                return ['success' => true, 'message' => 'Havuz kaydı silindi ve tampon stok güncellendi.'];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — SATIR GÜNCELLEME
    // ==========================================
    public function updatePoolTask(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id, $request) {
                $havuz = DB::table('tbBolumHavuz')->where('No', $id)->lockForUpdate()->first();
                if (!$havuz) {
                    return ['success' => false, 'message' => 'Havuz kaydı bulunamadı.'];
                }

                $bomService = app(BomService::class);
                $araUrunAdiNo = strval($havuz->AraUrunAdiNo);

                $newToplamAdet = intval($request->input('toplam_adet', $havuz->ToplamAdet));
                $newAciklama = $request->input('aciklama', $havuz->Aciklama ?? '');

                // ASP.NET AdminAnaSayfa RowUpdating: UretimAdetBelirle ile normalize
                $yol = $bomService->tumYolHazirla($araUrunAdiNo);
                $uretimAdet = $bomService->uretimAdetBelirle($araUrunAdiNo, $yol, $newToplamAdet);

                // ASP.NET: AdetBelirle ile üretilebilir adet hesapla
                $uretilebilecekUrunAdedi = $bomService->adetBelirle($araUrunAdiNo);
                if ($uretilebilecekUrunAdedi < 0 || $uretilebilecekUrunAdedi > $uretimAdet) {
                    $uretilebilecekUrunAdedi = $uretimAdet;
                }

                if ($uretimAdet <= 0) {
                    return ['success' => false, 'message' => 'Toplam adet sıfırdan büyük olmalıdır.'];
                }

                DB::table('tbBolumHavuz')->where('No', $id)->update([
                    'Adet' => $uretilebilecekUrunAdedi,
                    'ToplamAdet' => $uretimAdet,
                    'Aciklama' => $newAciklama,
                ]);

                return ['success' => true, 'message' => 'Havuz kaydı güncellendi.', 'ara_urun_no' => $araUrunAdiNo];
            });

            // Zincirleme havuz ve görev güncelleme
            if (($result['success'] ?? false) && !empty($result['ara_urun_no'])) {
                $bomService = app(BomService::class);
                $bomService->sonrakiUrunAdetleriniGuncelle($result['ara_urun_no']);
                $bomService->personelGorevTabloGuncelle($result['ara_urun_no']);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — SATIR EKLEME (Manuel)
    // ==========================================
    public function storePoolTask(Request $request)
    {
        try {
            $araUrunAdiNo = intval($request->input('ara_urun_no'));
            $urunIDNo = intval($request->input('urun_id_no', 0));
            $adet = intval($request->input('toplam_adet', $request->input('adet', 0)));
            $aciklama = $request->input('aciklama', '');

            if ($araUrunAdiNo <= 0 || $adet <= 0) {
                return response()->json(['success' => false, 'message' => 'Ara ürün no ve adet gerekli.'], 422);
            }

            $bomService = app(BomService::class);

            // Ara ürün bilgilerini al
            $araUrun = DB::table('tbAraUrun')->where('No', $araUrunAdiNo)->first();
            if (!$araUrun) {
                return response()->json(['success' => false, 'message' => 'Ara ürün bulunamadı.'], 404);
            }

            $bolumAdiNo = intval($araUrun->BolumAdiNo ?? 0);
            $araUrunAdi = $araUrun->AraUrunAdi ?? '';

            // UrunIDNo belirleme
            if ($urunIDNo <= 0) {
                $urunIDNo = intval(
                    DB::table('tbUrunler')
                        ->whereRaw("AraAdlarYol LIKE ?", ['%' . $araUrunAdiNo . '%'])
                        ->where('No', '!=', 502)
                        ->value('No') ?? 10
                );
            }

            // ASP.NET: AdetBelirle ile üretilebilir adet
            $yol = $bomService->tumYolHazirla(strval($araUrunAdiNo));
            $uretimAdet = $bomService->uretimAdetBelirle(strval($araUrunAdiNo), $yol, $adet);
            $uretilebilirAdet = $bomService->adetBelirle(strval($araUrunAdiNo));

            if ($uretilebilirAdet > $uretimAdet || $uretilebilirAdet < 0) {
                $uretilebilirAdet = $uretimAdet;
            }

            if ($uretimAdet > 0) {
                $guncelAciklama = $aciklama;
                if (!empty($guncelAciklama)) {
                    $bolumAdi = DB::table('tbBolum')->where('No', $bolumAdiNo)->value('BolumAdi') ?? '';
                    $guncelAciklama = $bolumAdi . ': ' . trim($guncelAciklama);
                }

                DB::table('tbBolumHavuz')->insert([
                    'UrunIDNo' => $urunIDNo,
                    'GorevBaslangicTarihi' => now()->format('d/m/Y'),
                    'GorevBaslangicSaati' => now()->format('H:i'),
                    'Adet' => $uretilebilirAdet,
                    'ToplamAdet' => $uretimAdet,
                    'BolumAdiNo' => $bolumAdiNo,
                    'Aciklama' => $guncelAciklama,
                    'AraUrunAdiNo' => $araUrunAdiNo,
                ]);

                // Tampon stok düşümü
                $bomService->araStokTamponAzalt(strval($araUrunAdiNo), $uretimAdet);

                return response()->json(['success' => true, 'message' => 'Havuz kaydı eklendi.']);
            }

            return response()->json(['success' => false, 'message' => 'Üretim adeti sıfır veya yetersiz stok.'], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — TOPLU SİLME (Ürün bazlı)
    // ==========================================
    public function deletePoolTasksByProduct(Request $request)
    {
        try {
            $urunIDNo = intval($request->input('urun_id_no'));
            if ($urunIDNo <= 0) {
                return response()->json(['success' => false, 'message' => 'Ürün No gerekli.']);
            }

            $affectedAraNos = [];

            $result = DB::transaction(function () use ($urunIDNo, &$affectedAraNos) {
                $rows = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->get();

                foreach ($rows as $row) {
                    $araNo = intval($row->AraUrunAdiNo);
                    if (!in_array($araNo, $affectedAraNos)) {
                        $affectedAraNos[] = $araNo;
                    }

                    // ASP.NET tamponStokKontrol: alt parçaların tamponlarını geri iade et
                    $bomService = app(BomService::class);
                    $bomService->tamponStokKontrol(strval($araNo), intval($row->ToplamAdet));
                }

                $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $urunIDNo)->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            // ASP.NET AdminAnaSayfa RowDeleting: personelGorevTabloGuncelle
            if (($result['success'] ?? false) && !empty($affectedAraNos)) {
                $bomService = app(BomService::class);
                foreach (array_unique($affectedAraNos) as $araNo) {
                    $bomService->personelGorevTabloGuncelle(strval($araNo));
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // HAVUZ — TOPLU SİLME (Tüm / Filtreli)
    // ==========================================
    public function deleteAllPoolTasks(Request $request)
    {
        try {
            $affectedAraNos = [];

            $result = DB::transaction(function () use ($request, &$affectedAraNos) {
                $query = DB::table('tbBolumHavuz');

                // ASP.NET btnDeleteAll_Click: görünüm moduna göre silme
                if ($request->filled('department_id')) {
                    $query->where('BolumAdiNo', intval($request->department_id));
                } elseif ($request->filled('ara_urun_no')) {
                    $query->where('AraUrunAdiNo', intval($request->ara_urun_no));
                } elseif ($request->filled('urun_id_no')) {
                    $query->where('UrunIDNo', intval($request->urun_id_no));
                }

                // Tampon stokları iade et (ASP.NET: Stoklar.tamponSifirla())
                $bomService = app(BomService::class);
                $rows = $query->get();
                foreach ($rows as $row) {
                    $araNo = intval($row->AraUrunAdiNo);
                    if (!in_array($araNo, $affectedAraNos)) {
                        $affectedAraNos[] = $araNo;
                    }

                    // ASP.NET tamponStokKontrol: alt parçaların tamponlarını geri iade et
                    $bomService->tamponStokKontrol(strval($araNo), intval($row->ToplamAdet));
                }

                // Silme işlemi (filtreli query'yi tekrar oluştur)
                $delQuery = DB::table('tbBolumHavuz');
                if ($request->filled('department_id')) {
                    $delQuery->where('BolumAdiNo', intval($request->department_id));
                } elseif ($request->filled('ara_urun_no')) {
                    $delQuery->where('AraUrunAdiNo', intval($request->ara_urun_no));
                } elseif ($request->filled('urun_id_no')) {
                    $delQuery->where('UrunIDNo', intval($request->urun_id_no));
                }

                $deleted = $delQuery->delete();
                return ['success' => true, 'message' => $deleted . ' havuz kaydı silindi.', 'deleted' => $deleted];
            });

            // ASP.NET: personelGorevTabloGuncelle
            if (($result['success'] ?? false) && !empty($affectedAraNos)) {
                $bomService = app(BomService::class);
                foreach (array_unique($affectedAraNos) as $araNo) {
                    $bomService->personelGorevTabloGuncelle(strval($araNo));
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // DEPARTMENTS (tbBolum)
    // ==========================================
    public function getDepartments(Request $request)
    {
        // Alt sorgular ile tek seferde personel + ara ürün sayıları
        $pSub = DB::table('tbPersonel')->selectRaw('BolumAdiNo, COUNT(*) as cnt')->groupBy('BolumAdiNo');
        $aSub = DB::table('tbAraUrun')->selectRaw('BolumAdiNo, COUNT(*) as cnt')->groupBy('BolumAdiNo');

        $query = DB::table('tbBolum as b')
            ->leftJoinSub($pSub, 'pc', fn($j) => $j->on('b.No', '=', 'pc.BolumAdiNo'))
            ->leftJoinSub($aSub, 'ac', fn($j) => $j->on('b.No', '=', 'ac.BolumAdiNo'))
            ->select('b.No', 'b.BolumAdi',
                DB::raw('IFNULL(pc.cnt, 0) as personnel_count'),
                DB::raw('IFNULL(ac.cnt, 0) as component_count'))
            ->orderBy('b.No', 'asc');

        if ($search = $request->input('search')) {
            $query->where('b.BolumAdi', 'like', "%$search%")
                  ->orWhere('b.No', 'like', "%$search%");
        }

        $depts = $query->get()->map(fn($d) => [
            'id'               => $d->No,
            'name'             => $d->BolumAdi,
            'bolum_no'         => $d->No,
            'personnel_count'  => (int)$d->personnel_count,
            'component_count'  => (int)$d->component_count,
        ]);
        return response()->json(['data' => $depts]);
    }

    public function storeDepartment(Request $request)
    {
        $request->validate(['name' => 'required']);
        $no = DB::table('tbBolum')->insertGetId([
            'BolumAdi' => $request->name
        ], 'No');
        return response()->json(['message' => 'Bölüm başarıyla eklendi', 'data' => ['id' => $no, 'name' => $request->name]]);
    }

    public function updateDepartment(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        DB::table('tbBolum')->where('No', $id)->update(['BolumAdi' => $request->name]);
        return response()->json(['message' => 'Bölüm güncellendi']);
    }

    public function deleteDepartment($id)
    {
        // Bölüme bağlı personel veya ara ürün varsa silme
        $personelSayisi = DB::table('tbPersonel')->where('BolumAdiNo', $id)->count();
        $araUrunSayisi  = DB::table('tbAraUrun')->where('BolumAdiNo', $id)->count();

        if ($personelSayisi > 0 || $araUrunSayisi > 0) {
            return response()->json([
                'message' => "Bu bölümde {$personelSayisi} personel ve {$araUrunSayisi} ara ürün kayıtlı. Önce bunları başka bir bölüme taşıyın."
            ], 422);
        }

        DB::table('tbBolum')->where('No', $id)->delete();
        return response()->json(['message' => 'Bölüm silindi']);
    }

    // ==========================================
    // COMPONENTS (tbAraUrun — Ara Ürünler)
    // ==========================================
    public function getComponents(Request $request)
    {
        $query = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->select('a.*', DB::raw("IFNULL(b.BolumAdi,'') as department_name"))
            ->orderByDesc('a.No');

        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo') && !$request->boolean('include_merged')) {
            $query->where(function ($q) {
                $q->whereNull('a.MergedIntoNo')
                    ->orWhere('a.MergedIntoNo', 0);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('a.AraUrunAdi', 'like', "%$search%")
                  ->orWhere('a.No', 'like', "%$search%");
            });
        }

        $data = $query->get()->map(function($comp) {
            return [
                'id' => $comp->No,
                'name' => $comp->AraUrunAdi,
                'performance_score' => $comp->Performans,
                'min_quantity' => $comp->MinAdet,
                'type' => $comp->UrunCesidi,
                'path' => $comp->Yol,
                'image' => $comp->Resim ?? '',
                'department_id' => $comp->BolumAdiNo,
                'department_name' => $comp->department_name,
                'merged_into_no' => intval($comp->MergedIntoNo ?? 0),
                'merged_at' => $comp->MergedAt ?? null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function storeComponent(Request $request)
    {
        $request->validate(['name' => 'required']);

        // ASP.NET YeniUrunEkle: name_path (ad-adet:ad-adet) -> ID path (no-adet:no-adet)
        $path = $request->path ?? '';
        if ($request->filled('name_path')) {
            $converted = $this->convertYolNamesToIds($request->name_path);
            if (str_contains($converted, '#')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ürün Yolu için girilen ürün adını kaydediniz...'
                ], 422);
            }
            $path = $converted;
        }

        $imagePath = $request->image ?? '';
        if ($request->hasFile('image_file')) {
            $request->validate(['image_file' => 'mimes:jpg,jpeg,png|max:2048']);
            $imagePath = $this->saveLegacyProductImage($request->file('image_file'));
        }

        $no = DB::table('tbAraUrun')->insertGetId([
            'AraUrunAdi' => $request->name,
            'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0,
            'UrunCesidi' => $request->type ?? '',
            'Yol' => $path,
            'Resim' => $imagePath,
            'BolumAdiNo' => $request->department_id ?: null,
        ], 'No');

        // Nihai Ürün ise tbUrunler'e de ekle (ASP.NET: UrunlerTablosunaEkle)
        if ($this->isFinalProductType($request->type ?? '')) {
            $this->urunlerTablosunaEkle($request->name);
        }

        return response()->json(['success' => true, 'message' => 'Ara ürün eklendi', 'id' => $no]);
    }

    public function updateComponent(Request $request, $id)
    {
        $update = [
            'AraUrunAdi' => $request->name,
            'Performans' => $request->performance_score ?? 0,
            'MinAdet' => $request->min_quantity ?? 0,
            'UrunCesidi' => $request->type ?? '',
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
        } catch (\Throwable) {
            // BOM kaydı tamamlandı; görev senkronizasyonu ana düzenlemeyi engellemesin.
        }

        return response()->json([
            'success' => true,
            'message' => 'Ürün ağacı kaydedildi.',
            'id' => $componentId,
            'path' => $path,
        ]);
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
        } catch (\Throwable) {
            // BOM kaydı tamamlandı; görev senkronizasyonu ana düzenlemeyi engellemesin.
        }

        return response()->json([
            'success' => true,
            'message' => 'Ürün ağacı temizlendi.',
            'id' => $componentId,
            'path' => '',
        ]);
    }

    public function deleteComponent($id)
    {
        // Başka ürünün yolunda kullanılıyor mu kontrol et
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

    // ==========================================
    // PRODUCTS (tbUrunler — Nihai Ürünler)
    // ==========================================
    public function getProducts(Request $request)
    {
        $query = DB::table('tbUrunler')->orderByDesc('No');
        if (Schema::hasColumn('tbUrunler', 'MergedIntoNo') && !$request->boolean('include_merged')) {
            $query->where(function ($q) {
                $q->whereNull('MergedIntoNo')
                    ->orWhere('MergedIntoNo', 0);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('UrunID', 'like', "%$search%")
                  ->orWhere('SistemAdi', 'like', "%$search%");
            });
        }
        $data = $query->get()->map(function($p) {
            return [
                'id' => $p->No,
                'name' => $p->UrunID,
                'system_name' => $p->SistemAdi,
                'system_code' => $p->SistemKodu,
                'path' => $p->AraAdlarYol,
                'image' => $p->Resim ?? '',
                'merged_into_no' => intval($p->MergedIntoNo ?? 0),
                'merged_at' => $p->MergedAt ?? null,
            ];
        });
        return response()->json(['data' => $data]);
    }

    public function storeProduct(Request $request)
    {
        $name = $request->input('name', $request->input('UrunID'));
        if (!$name) {
            return response()->json(['message' => 'Ürün adı gerekli'], 422);
        }

        $no = DB::table('tbUrunler')->insertGetId([
            'UrunID' => $name,
            'SistemAdi' => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu' => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ], 'No');
        return response()->json(['success' => true, 'message' => 'Ürün eklendi', 'id' => $no]);
    }

    public function previewProductMerge(Request $request, ProductMergeService $mergeService)
    {
        $data = $request->validate([
            'merge_type' => 'required|string',
            'source_id' => 'required|integer|min:1',
            'target_id' => 'required|integer|min:1',
            'include_linked_component' => 'sometimes|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'preview' => $mergeService->preview(
                    (string) $data['merge_type'],
                    intval($data['source_id']),
                    intval($data['target_id']),
                    $request->boolean('include_linked_component')
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Birleştirme önizlemesi hazırlanamadı: ' . $e->getMessage()], 500);
        }
    }

    public function mergeProducts(Request $request, ProductMergeService $mergeService)
    {
        $data = $request->validate([
            'merge_type' => 'required|string',
            'source_id' => 'required|integer|min:1',
            'target_id' => 'required|integer|min:1',
            'include_linked_component' => 'sometimes|boolean',
            'confirm' => 'required|boolean',
        ]);

        if (!$request->boolean('confirm')) {
            return response()->json(['success' => false, 'message' => 'Birleştirme onayı gerekli.'], 422);
        }

        try {
            $result = $mergeService->merge(
                (string) $data['merge_type'],
                intval($data['source_id']),
                intval($data['target_id']),
                $request->user(),
                $request->boolean('include_linked_component')
            );

            return response()->json([
                'success' => true,
                'message' => 'Ürün birleştirme tamamlandı.',
                'result' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Birleştirme tamamlanamadı: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request, $id)
    {
        $name = $request->input('name', $request->input('UrunID'));
        if (empty(trim((string) $name))) {
            return response()->json(['message' => 'Ürün adı boş olamaz.'], 422);
        }

        DB::table('tbUrunler')->where('No', $id)->update([
            'UrunID'      => $name,
            'SistemAdi'   => $request->input('system_name', $request->input('SistemAdi', '')),
            'SistemKodu'  => $request->input('system_code', $request->input('SistemKodu', '')),
            'AraAdlarYol' => $request->input('path', $request->input('AraAdlarYol', '')),
        ]);
        return response()->json(['success' => true, 'message' => 'Ürün güncellendi']);
    }

    public function deleteProduct($id)
    {
        // Aktif siparişi olan ürün silinemesin
        $urun = DB::table('tbUrunler')->where('No', $id)->first();
        if (!$urun) {
            return response()->json(['message' => 'Ürün bulunamadı.'], 404);
        }

        $aktifSiparisSayisi = DB::table('tbSiparisSatir')
            ->where('UrunID', $urun->UrunID)
            ->whereNotIn('Durum', ['Tamamlandi', 'Iptal', 'StokKarsilandi', 'UretimdenKarsilaniyor'])
            ->count();

        if ($aktifSiparisSayisi > 0) {
            return response()->json([
                'message' => "Bu ürünün {$aktifSiparisSayisi} aktif siparişi bulunuyor. Siparişler tamamlanmadan ürün silinemez."
            ], 422);
        }

        // Havuzda veya personel görevinde kullanılıyor mu?
        $havuzSayisi = DB::table('tbBolumHavuz')->where('UrunIDNo', $id)->count();
        if ($havuzSayisi > 0) {
            return response()->json([
                'message' => "Bu ürün üretim havuzunda {$havuzSayisi} görevle kullanılıyor. Önce havuzu temizleyin."
            ], 422);
        }

        DB::table('tbUrunler')->where('No', $id)->delete();
        return response()->json(['message' => 'Ürün silindi']);
    }

    // ==========================================
    // EXCEL / CSV IMPORT (Veritabani yonetimi)
    // ==========================================
    public function importModule(Request $request, string $module)
    {
        if (!in_array($module, ['personnel', 'departments', 'components', 'products'], true)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz içeri aktarma modülü.'], 404);
        }

        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => is_array($row) && $this->importRowHasContent($row))
            ->values();

        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'İçeri aktarılacak satır bulunamadı.'], 422);
        }

        $summary = [
            'success' => true,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $nextPersonnelNo = $module === 'personnel'
            ? ((int) (DB::table('tbPersonel')->max('PersonelNo') ?? 0)) + 1
            : 1;

        DB::transaction(function () use ($rows, $module, &$summary, &$nextPersonnelNo) {
            foreach ($rows as $index => $row) {
                try {
                    $result = match ($module) {
                        'personnel' => $this->importPersonnelRow($row, $nextPersonnelNo),
                        'departments' => $this->importDepartmentRow($row),
                        'components' => $this->importComponentRow($row),
                        'products' => $this->importProductRow($row),
                    };

                    if ($result === 'inserted') {
                        $summary['inserted']++;
                    } elseif ($result === 'updated') {
                        $summary['updated']++;
                    } else {
                        $summary['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $summary['skipped']++;
                    if (count($summary['errors']) < 10) {
                        $summary['errors'][] = 'Satır ' . ($index + 2) . ': ' . $e->getMessage();
                    }
                }
            }
        });

        $summary['message'] = "{$summary['inserted']} eklendi, {$summary['updated']} güncellendi, {$summary['skipped']} atlandı.";

        return response()->json($summary);
    }

    private function importPersonnelRow(array $row, int &$nextPersonnelNo): string
    {
        $personnelNo = $this->importInteger($this->importValue($row, 'id', 'PersonelNo', 'Personel No', 'No'));
        $name = $this->importText($row, 'name', 'Ad', 'Personel Adı', 'Personel Adi');
        $surname = $this->importText($row, 'surname', 'Soyad');
        $address = $this->importText($row, 'address', 'Adres');
        $phone = $this->importText($row, 'phone', 'Telefon');
        $email = $this->importText($row, 'email', 'Mail', 'E-posta', 'Eposta');
        $departmentId = $this->importDepartmentId($row);

        if (!$personnelNo) {
            if ($name === null && $email === null) {
                throw new \RuntimeException('Personel adı veya mail alanı boş.');
            }
            $personnelNo = $nextPersonnelNo++;
        } elseif ($personnelNo >= $nextPersonnelNo) {
            $nextPersonnelNo = $personnelNo + 1;
        }

        $exists = DB::table('tbPersonel')->where('PersonelNo', $personnelNo)->exists();

        $values = [];
        if ($name !== null) $values['Ad'] = $name;
        if ($surname !== null) $values['Soyad'] = $surname;
        if ($address !== null) $values['Adres'] = $address;
        if ($phone !== null) $values['Telefon'] = $phone;
        if ($email !== null) $values['Mail'] = $email;
        if ($departmentId !== null) $values['BolumAdiNo'] = $departmentId;

        $password = $this->importText($row, 'new_password', 'password', 'Sifre', 'Şifre');
        if ($password !== null && $password !== '***') {
            $values['Sifre'] = $this->normalizeImportedPassword($password);
        }

        if ($exists) {
            if (empty($values)) {
                return 'skipped';
            }

            DB::table('tbPersonel')->where('PersonelNo', $personnelNo)->update($values);
            return 'updated';
        }

        DB::table('tbPersonel')->insert(array_merge([
            'PersonelNo' => $personnelNo,
            'Ad' => $name ?? '',
            'Soyad' => $surname ?? '',
            'Adres' => $address ?? '',
            'Telefon' => $phone ?? '',
            'Mail' => $email ?? '',
            'Sifre' => Hash::make('123'),
            'BolumAdiNo' => $departmentId,
        ], $values));

        return 'inserted';
    }

    private function importDepartmentRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'bolum_no', 'No', 'Bölüm No', 'Bolum No'));
        $name = $this->importText($row, 'name', 'BolumAdi', 'Bolum Adi', 'Bölüm Adı');

        if ($name === null) {
            throw new \RuntimeException('Bölüm adı boş.');
        }

        if ($id && DB::table('tbBolum')->where('No', $id)->exists()) {
            DB::table('tbBolum')->where('No', $id)->update(['BolumAdi' => $name]);
            return 'updated';
        }

        $insert = ['BolumAdi' => $name];
        if ($id) {
            $insert['No'] = $id;
            DB::table('tbBolum')->insert($insert);
        } else {
            DB::table('tbBolum')->insertGetId($insert, 'No');
        }

        return 'inserted';
    }

    private function importComponentRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'No', 'AraUrunNo', 'Ara Ürün No', 'Ara Urun No'));
        $name = $this->importText($row, 'name', 'AraUrunAdi', 'Ara Urun Adi', 'Ara Ürün Adı', 'Bileşen', 'Bilesen');
        $type = $this->importText($row, 'type', 'UrunCesidi', 'Urun Cesidi', 'Ürün Çeşidi', 'Tür', 'Tur');
        $path = $this->importText($row, 'path', 'Yol', 'BOM', 'BOM Yolu');
        $image = $this->importText($row, 'image', 'Resim');
        $departmentId = $this->importDepartmentId($row);
        $performance = $this->importInteger($this->importValue($row, 'performance_score', 'Performans'));
        $minQuantity = $this->importInteger($this->importValue($row, 'min_quantity', 'MinAdet', 'Min Adet'));

        $exists = $id ? DB::table('tbAraUrun')->where('No', $id)->exists() : false;
        if (!$exists && $name === null) {
            throw new \RuntimeException('Ara ürün adı boş.');
        }

        $values = [];
        if ($name !== null) $values['AraUrunAdi'] = $name;
        if ($performance !== null) $values['Performans'] = $performance;
        if ($minQuantity !== null) $values['MinAdet'] = $minQuantity;
        if ($type !== null) $values['UrunCesidi'] = $type;
        if ($path !== null) $values['Yol'] = $path;
        if ($image !== null && Schema::hasColumn('tbAraUrun', 'Resim')) $values['Resim'] = $image;
        if ($departmentId !== null) $values['BolumAdiNo'] = $departmentId ?: null;

        if ($exists) {
            if (empty($values)) {
                return 'skipped';
            }

            DB::table('tbAraUrun')->where('No', $id)->update($values);
            $this->syncFinalProductFromComponent($name, $type);
            return 'updated';
        }

        $insert = array_merge([
            'AraUrunAdi' => $name,
            'Performans' => $performance ?? 0,
            'MinAdet' => $minQuantity ?? 0,
            'UrunCesidi' => $type ?? '',
            'Yol' => $path ?? '',
            'BolumAdiNo' => $departmentId ?: null,
        ], $id ? ['No' => $id] : []);

        if (Schema::hasColumn('tbAraUrun', 'Resim')) {
            $insert['Resim'] = $image ?? '';
        }

        if ($id) {
            DB::table('tbAraUrun')->insert($insert);
        } else {
            DB::table('tbAraUrun')->insertGetId($insert, 'No');
        }

        $this->syncFinalProductFromComponent($name, $type);

        return 'inserted';
    }

    private function importProductRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'No', 'UrunNo', 'Ürün No', 'Urun No'));
        $name = $this->importText($row, 'name', 'UrunID', 'Urun ID', 'Ürün Adı', 'Urun Adi', 'Nihai Ürün Adı', 'Nihai Urun Adi');
        $systemName = $this->importText($row, 'system_name', 'SistemAdi', 'Sistem Adi', 'Sistem Adı');
        $systemCode = $this->importText($row, 'system_code', 'SistemKodu', 'Sistem Kodu');
        $path = $this->importText($row, 'path', 'AraAdlarYol', 'Ara Adlar Yol', 'Yol', 'BOM', 'BOM Yolu');
        $image = $this->importText($row, 'image', 'Resim');

        $exists = $id ? DB::table('tbUrunler')->where('No', $id)->exists() : false;
        if (!$exists && $name === null) {
            throw new \RuntimeException('Ürün adı boş.');
        }

        $values = [];
        if ($name !== null) $values['UrunID'] = $name;
        if ($systemName !== null) $values['SistemAdi'] = $systemName;
        if ($systemCode !== null) $values['SistemKodu'] = $systemCode;
        if ($path !== null) $values['AraAdlarYol'] = $path;
        if ($image !== null && Schema::hasColumn('tbUrunler', 'Resim')) $values['Resim'] = $image;

        if ($exists) {
            if (empty($values)) {
                return 'skipped';
            }

            DB::table('tbUrunler')->where('No', $id)->update($values);
            return 'updated';
        }

        $insert = array_merge([
            'UrunID' => $name,
            'SistemAdi' => $systemName ?? '',
            'SistemKodu' => $systemCode ?? '',
            'AraAdlarYol' => $path ?? '',
        ], $id ? ['No' => $id] : []);

        if ($image !== null && Schema::hasColumn('tbUrunler', 'Resim')) {
            $insert['Resim'] = $image;
        }

        if ($id) {
            DB::table('tbUrunler')->insert($insert);
        } else {
            DB::table('tbUrunler')->insertGetId($insert, 'No');
        }

        return 'inserted';
    }

    private function importRowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function importValue(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) ($row[$key] ?? '')) !== '') {
                return $row[$key];
            }
        }

        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedRow[$this->normalizeImportKey((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeImportKey($key);
            if (array_key_exists($normalizedKey, $normalizedRow) && trim((string) ($normalizedRow[$normalizedKey] ?? '')) !== '') {
                return $normalizedRow[$normalizedKey];
            }
        }

        return null;
    }

    private function importText(array $row, string ...$keys): ?string
    {
        $value = $this->importValue($row, ...$keys);
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function importInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (is_numeric($normalized)) {
            return (int) round((float) $normalized);
        }

        $digits = preg_replace('/[^\d-]+/', '', $normalized);
        return $digits === '' ? null : (int) $digits;
    }

    private function importDepartmentId(array $row): ?int
    {
        $departmentId = $this->importInteger($this->importValue(
            $row,
            'department_id',
            'BolumAdiNo',
            'Bolum No',
            'Bölüm No',
            'department'
        ));

        if ($departmentId !== null) {
            return $departmentId;
        }

        $departmentName = $this->importText($row, 'department_name', 'BolumAdi', 'Bolum Adi', 'Bölüm Adı', 'Bölüm', 'Bolum');
        if ($departmentName === null) {
            return null;
        }

        $resolvedId = DB::table('tbBolum')->where('BolumAdi', $departmentName)->value('No');
        return $resolvedId !== null ? (int) $resolvedId : null;
    }

    private function normalizeImportKey(string $key): string
    {
        $key = strtr($key, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ş' => 's', 'ş' => 's',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);
        $key = strtolower($key);

        return preg_replace('/[^a-z0-9]+/', '', $key) ?? '';
    }

    private function normalizeImportedPassword(string $password): string
    {
        if (str_starts_with($password, '$2y$') || str_starts_with($password, '$argon2')) {
            return $password;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $password)) {
            return $password;
        }

        return Hash::make($password);
    }

    private function syncFinalProductFromComponent(?string $name, ?string $type): void
    {
        if ($name === null || !in_array($this->normalizeImportKey((string) $type), ['nihayiurun', 'nihaiurun'], true)) {
            return;
        }

        $this->urunlerTablosunaEkle($name);
    }

    // ==========================================
    // RESİM YÜKLEME
    // ==========================================
    public function uploadProductImage(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:2048']); // Max 2MB

        if ($request->file('image')) {
            $path = $request->file('image')->store('products', 'public');

            DB::table('tbUrunler')->where('No', $id)->update([
                'Resim' => '/storage/' . $path
            ]);

            return response()->json(['success' => true, 'message' => 'Resim başarıyla yüklendi', 'path' => '/storage/' . $path]);
        }

        return response()->json(['success' => false, 'message' => 'Resim alınamadı'], 400);
    }

    // ==========================================
    // BOM PATH WITH NAMES (YeniUrunDuzenle desteği)
    // ==========================================

    /**
     * ASP.NET UrunCiz.TumYolHazirla karşılığı — isimlerle döndürür.
     * Format: "KaynakAdı-HedefAdı-Çarpan:..."
     */
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

            // ASP.NET yukle(): yaprak (leaf) bileşenleri bul
            $targetNames = collect($edges)->pluck('target_name')->unique()->all();
            $replaceables = collect($edges)
                ->pluck('source_name')
                ->unique()
                ->filter(fn ($source) => !in_array($source, $targetNames, true))
                ->sort()
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'namePath' => $namePath,
                'idPath' => $idPath,
                'edges' => $edges,
                'replaceables' => $replaceables
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Nihai Ürün (tbUrunler) için BOM ağacını isim bazlı döndürür.
     * AraAdlarYol (ID path) → name path dönüşümü.
     */
    public function getProductBomPathNames($id)
    {
        try {
            $product = DB::table('tbUrunler')->where('No', $id)->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
            }

            $idPath = $product->AraAdlarYol ?? '';
            $componentId = intval(DB::table('tbAraUrun')->where('AraUrunAdi', $product->UrunID)->value('No') ?? 0);

            if (empty(trim($idPath))) {
                return response()->json([
                    'success' => true,
                    'product_name' => $product->UrunID,
                    'component_id' => $componentId,
                    'namePath' => '',
                    'idPath' => '',
                    'edges' => [],
                    'replaceables' => [],
                ]);
            }

            $namePath = $this->idPathToNamePath($idPath);
            $edges = $this->idPathToBomEdges($idPath);
            $targetNames = collect($edges)->pluck('target_name')->unique()->all();
            $replaceables = collect($edges)
                ->pluck('source_name')
                ->unique()
                ->filter(fn ($source) => !in_array($source, $targetNames, true))
                ->sort()
                ->values()
                ->all();

            return response()->json([
                'success'      => true,
                'product_name' => $product->UrunID,
                'component_id' => $componentId,
                'namePath'     => $namePath,
                'idPath'       => $idPath,
                'edges'        => $edges,
                'replaceables' => $replaceables,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // ÜRÜN TÜRETME (YeniUrunDuzenle.aspx)
    // ==========================================

    /**
     * ASP.NET YeniUrunDuzenle.aspx Button1_Click karşılığı.
     * Orijinal yol ile değiştirilmiş yolu kıyaslar, değişen bileşenleri klonlar.
     */
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
            if (!$sourceComponent) {
                return response()->json(['success' => false, 'message' => 'Kaynak ara ürün bulunamadı.'], 404);
            }

            $originalIdPath = $bomService->tumYolHazirla(strval($sourceId));
            $originalNamePath = $this->idPathToNamePath($originalIdPath);

            $origSegments = $this->splitPathSegments($originalNamePath);
            $newSegments = $this->splitPathSegments($newNamePath);
            if (empty($origSegments) || empty($newSegments)) {
                return response()->json(['success' => false, 'message' => 'Üretim yolu bulunamadı.'], 422);
            }

            $createdComponents = [];

            DB::transaction(function () use (
                $origSegments, $newSegments, $sourceId, $imagePath,
                &$createdComponents, $sourceComponent, $newNamePath
            ) {
                // Faz 1: Değişen isimleri tespit et ve klonla (ASP.NET: Button1_Click loop)
                $segmentCount = min(count($origSegments), count($newSegments));
                for ($i = 0; $i < $segmentCount; $i++) {
                    $origParts = explode('-', $origSegments[$i]);
                    $newParts = explode('-', $newSegments[$i]);

                    for ($j = 0; $j < 2 && $j < count($origParts) && $j < count($newParts); $j++) {
                        $origName = trim($origParts[$j]);
                        $newName = trim($newParts[$j]);
                        if ($origName === '' || $newName === '') {
                            continue;
                        }

                        if ($origName !== $newName) {
                            $existing = DB::table('tbAraUrun')->where('AraUrunAdi', $newName)->first();
                            if (!$existing) {
                                $origNo = intval(DB::table('tbAraUrun')->where('AraUrunAdi', $origName)->value('No'));
                                $origRecord = $origNo > 0 ? DB::table('tbAraUrun')->where('No', $origNo)->first() : null;

                                if ($origRecord) {
                                    // ASP.NET: WebForm1.YoluBul — bu bileşenin alt girdilerini bul
                                    $newYol = $this->findComponentYolInPath($newName, $newNamePath);

                                    $resim = $origRecord->Resim;
                                    if ($sourceComponent && $sourceComponent->AraUrunAdi === $origName && !empty($imagePath)) {
                                        $resim = $imagePath;
                                    }

                                    DB::table('tbAraUrun')->insert([
                                        'AraUrunAdi' => $newName,
                                        'Performans' => $origRecord->Performans,
                                        'BolumAdiNo' => $origRecord->BolumAdiNo,
                                        'MinAdet' => $origRecord->MinAdet,
                                        'UrunCesidi' => $origRecord->UrunCesidi,
                                        'Resim' => $resim,
                                        'Yol' => $newYol, // Henüz isim formatında
                                    ]);

                                    $createdComponents[] = $newName;
                                }
                            }
                        }
                    }
                }

                // Faz 2: Yeni oluşturulan bileşenlerin Yol'unu isimden ID'ye çevir
                // ASP.NET: WebForm1.YoluDonustur2(existingProduct.Yol, "AraUrunAdi", "No")
                foreach (array_values(array_unique($createdComponents)) as $componentName) {
                    $component = DB::table('tbAraUrun')->where('AraUrunAdi', $componentName)->first();
                    if (!$component) {
                        continue;
                    }

                    if (!empty($component->Yol)) {
                        $convertedYol = $this->convertYolNamesToIds($component->Yol);
                        if (!str_contains($convertedYol, '#')) {
                            DB::table('tbAraUrun')->where('No', $component->No)->update(['Yol' => $convertedYol]);
                            $component->Yol = $convertedYol;
                        }
                    }

                    // ASP.NET: Nihayi Ürün ise UrunlerTablosunaEkle
                    if ($this->isFinalProductType($component->UrunCesidi ?? '')) {
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

    // ==========================================
    // YARDIMCI METODLAR (YOL DÖNÜŞÜM)
    // ==========================================

    /**
     * ID path → Name path dönüşümü.
     * "5-10-3:10-15-1" → "Sünger-Koltuk-3:Koltuk-Takım-1"
     */
    private function idPathToNamePath(string $idPath): string
    {
        if (empty($idPath)) return '';

        $allNos = [];
        foreach (explode(':', $idPath) as $seg) {
            foreach (explode('-', $seg) as $i => $part) {
                if ($i < 2) $allNos[] = intval($part);
            }
        }
        $nameMap = DB::table('tbAraUrun')
            ->whereIn('No', array_unique($allNos))
            ->pluck('AraUrunAdi', 'No')
            ->toArray();

        $result = [];
        foreach (explode(':', $idPath) as $seg) {
            $parts = explode('-', $seg);
            if (count($parts) >= 3) {
                $sourceName = $nameMap[intval($parts[0])] ?? $parts[0];
                $targetName = $nameMap[intval($parts[1])] ?? $parts[1];
                $result[] = $sourceName . '-' . $targetName . '-' . $parts[2];
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

            $segments[] = [
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'quantity' => $parts[2],
            ];
            $allNos[] = $sourceId;
            $allNos[] = $targetId;
        }

        if (empty($segments)) return [];

        $componentMap = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->whereIn('a.No', array_unique($allNos))
            ->select(
                'a.No',
                'a.AraUrunAdi',
                'a.UrunCesidi',
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get()
            ->keyBy('No');

        return array_map(function ($edge) use ($componentMap) {
            $source = $componentMap->get($edge['source_id']);
            $target = $componentMap->get($edge['target_id']);

            return [
                'source_id' => $edge['source_id'],
                'source_name' => $source->AraUrunAdi ?? strval($edge['source_id']),
                'source_department_name' => $source->department_name ?? '',
                'source_type' => $source->UrunCesidi ?? '',
                'target_id' => $edge['target_id'],
                'target_name' => $target->AraUrunAdi ?? strval($edge['target_id']),
                'target_department_name' => $target->department_name ?? '',
                'target_type' => $target->UrunCesidi ?? '',
                'quantity' => $edge['quantity'],
            ];
        }, $segments);
    }

    /**
     * Name path → ID path dönüşümü (YeniUrunEkle: Yol alanı).
     * "Sünger-3:İskelet-1" → "5-3:10-1"
     * ASP.NET: WebForm1.YoluDonustur2
     */
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
                $no = ctype_digit($source)
                    ? $source
                    : DB::table('tbAraUrun')->where('AraUrunAdi', $source)->value('No');
                if ($no) {
                    $result[] = $no . '-' . $parts[1];
                } else {
                    $result[] = '#' . $source . '-' . $parts[1];
                }
            }
        }
        return implode(':', $result);
    }

    private function splitPathSegments(string $path): array
    {
        return collect(explode(':', $path))
            ->map(fn ($segment) => trim($segment))
            ->filter(fn ($segment) => $segment !== '')
            ->values()
            ->all();
    }

    private function normalizeBomPathString(string $path, int $ownerId): string
    {
        $items = [];
        foreach ($this->splitPathSegments($path) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $items[] = [
                'component_id' => $parts[0] ?? null,
                'quantity' => $parts[1] ?? 1,
            ];
        }

        return $this->normalizeBomPathItems($items, $ownerId);
    }

    private function normalizeBomPathItems(mixed $items, int $ownerId): string
    {
        if (!is_array($items)) {
            throw new \InvalidArgumentException('Ürün ağacı satırları geçersiz.');
        }

        $segments = [];
        $childIds = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Ürün ağacı satırları geçersiz.');
            }

            $rawComponentId = trim((string) ($item['component_id'] ?? $item['id'] ?? $item['No'] ?? ''));
            $rawQuantity = trim((string) ($item['quantity'] ?? $item['adet'] ?? $item['Adet'] ?? '1'));

            if ($rawComponentId === '') {
                continue;
            }

            $componentId = intval($rawComponentId);
            $quantity = intval($rawQuantity);

            if ($componentId <= 0) {
                throw new \InvalidArgumentException('Alt parça seçimi geçersiz.');
            }
            if ($componentId === $ownerId) {
                throw new \InvalidArgumentException('Bir ürün kendi alt parçası olamaz.');
            }
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('Adet sıfırdan büyük olmalı.');
            }
            if (isset($seen[$componentId])) {
                throw new \InvalidArgumentException('Aynı alt parça birden fazla eklenemez. Adedi tek satırdan artırın.');
            }
            if ($this->wouldCreateBomCycle($ownerId, $componentId)) {
                throw new \InvalidArgumentException('Bu seçim ürün ağacında döngü oluşturur.');
            }

            $seen[$componentId] = true;
            $childIds[] = $componentId;
            $segments[] = $componentId . '-' . $quantity;
        }

        if ($childIds) {
            $existingIds = DB::table('tbAraUrun')
                ->whereIn('No', $childIds)
                ->pluck('No')
                ->map(fn ($value) => intval($value))
                ->all();

            $missingIds = array_diff($childIds, $existingIds);
            if ($missingIds) {
                throw new \InvalidArgumentException('Seçilen alt parçalardan biri bulunamadı.');
            }
        }

        return implode(':', $segments);
    }

    private function wouldCreateBomCycle(int $ownerId, int $candidateChildId): bool
    {
        if ($ownerId === $candidateChildId) {
            return true;
        }

        return $this->bomDescendantContains($candidateChildId, $ownerId, []);
    }

    private function bomDescendantContains(int $componentId, int $needleId, array $visited): bool
    {
        if (isset($visited[$componentId])) {
            return false;
        }

        $visited[$componentId] = true;
        $path = (string) (DB::table('tbAraUrun')->where('No', $componentId)->value('Yol') ?? '');

        foreach ($this->splitPathSegments($path) as $segment) {
            $childId = intval(explode('-', $segment)[0] ?? 0);
            if ($childId <= 0) {
                continue;
            }
            if ($childId === $needleId) {
                return true;
            }
            if ($this->bomDescendantContains($childId, $needleId, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function saveLegacyProductImage($file): string
    {
        $fileName = basename($file->getClientOriginalName());
        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
        $targetDir = $documentRoot !== '' && is_dir($documentRoot)
            ? $documentRoot . DIRECTORY_SEPARATOR . 'Resimler'
            : public_path('Resimler');

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $fileName);

        return $fileName;
    }

    private function isFinalProductType(?string $type): bool
    {
        $normalized = $this->normalizeImportKey((string) $type);

        return in_array($normalized, ['nihayiurun', 'nihaiurun'], true);
    }

    /**
     * Tam yol içinde bir bileşenin alt girdilerini bulur.
     * ASP.NET: WebForm1.YoluBul
     * Örnek: findComponentYolInPath("Koltuk", "Sünger-Koltuk-3:Kumaş-Koltuk-1:Koltuk-Takım-2")
     * Döndürür: "Sünger-3:Kumaş-1" (Koltuk'un girdileri)
     */
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

    /**
     * Nihayi Ürün → tbUrunler tablosuna ekle.
     * ASP.NET: WebForm1.UrunlerTablosunaEkle
     */
    private function urunlerTablosunaEkle(string $urunAdi): void
    {
        $araUrunler = DB::table('tbAraUrun')->where('AraUrunAdi', $urunAdi)->get();
        if ($araUrunler->isEmpty()) {
            return;
        }

        $bomService = app(BomService::class);
        foreach ($araUrunler as $araUrun) {
            $tumYol = $bomService->tumYolHazirla((string) $araUrun->No);

            DB::table('tbUrunler')->where('UrunID', $araUrun->AraUrunAdi)->delete();
            DB::table('tbUrunler')->insert([
                'UrunID' => $araUrun->AraUrunAdi,
                'AraAdlarYol' => $tumYol,
                'SistemAdi' => $araUrun->AraUrunAdi,
                'Resim' => $araUrun->Resim ?? '',
            ]);
        }
    }
}
