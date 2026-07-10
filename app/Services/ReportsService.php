<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Personnel;

class ReportsService
{
    public function pendingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    public function approvedApprovalSql(string $column = 'Onay'): string
    {
        return "({$column} = 1 OR {$column} = '1' OR LOWER(TRIM(CAST({$column} AS CHAR))) = 'true')";
    }

    public function legacyDateSql(string $column): string
    {
        $value = "NULLIF(TRIM(CAST({$column} AS CHAR)), '')";

        return "COALESCE("
            . "STR_TO_DATE({$value}, '%d/%m/%Y %H:%i:%s'), "
            . "STR_TO_DATE({$value}, '%d.%m.%Y %H:%i:%s'), "
            . "STR_TO_DATE({$value}, '%d/%m/%Y %H:%i'), "
            . "STR_TO_DATE({$value}, '%d.%m.%Y %H:%i'), "
            . "STR_TO_DATE({$value}, '%d/%m/%Y'), "
            . "STR_TO_DATE({$value}, '%d.%m.%Y'), "
            . "STR_TO_DATE({$value}, '%Y-%m-%d %H:%i:%s'), "
            . "STR_TO_DATE({$value}, '%Y-%m-%dT%H:%i:%s'), "
            . "STR_TO_DATE({$value}, '%Y-%m-%d')"
            . ")";
    }

    public function legacyFullNameSql(string $firstColumn = 'p.Ad', string $lastColumn = 'p.Soyad'): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "COALESCE({$firstColumn}, '') || ' ' || COALESCE({$lastColumn}, '')";
        }

        return "CONCAT(IFNULL({$firstColumn}, ''), ' ', IFNULL({$lastColumn}, ''))";
    }

    public function legacyHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public function legacyHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    public function personnelTaskDateColumn(): ?string
    {
        foreach (['GorevBaslamaTarihi', 'GorevBaslangicTarihi', 'GorevTarihi'] as $column) {
            if ($this->legacyHasColumn('tbPersonelGorev', $column)) {
                return $column;
            }
        }

        return null;
    }

    public function personnelTaskProductJoinAvailable(): bool
    {
        return $this->legacyHasTable('tbUrunler')
            && $this->legacyHasColumn('tbPersonelGorev', 'UrunIDNo')
            && $this->legacyHasColumn('tbUrunler', 'No');
    }

    public function personnelTaskPersonnelJoinAvailable(): bool
    {
        return $this->legacyHasTable('tbPersonel')
            && $this->legacyHasColumn('tbPersonelGorev', 'PersonelNo')
            && $this->legacyHasColumn('tbPersonel', 'PersonelNo');
    }

    public function personnelTaskDepartmentJoinAvailable(): bool
    {
        return $this->legacyHasTable('tbBolum')
            && $this->legacyHasColumn('tbPersonelGorev', 'BolumAdiNo')
            && $this->legacyHasColumn('tbBolum', 'No');
    }

    public function personnelTaskComponentJoinAvailable(): bool
    {
        return $this->legacyHasTable('tbAraUrun')
            && $this->legacyHasColumn('tbPersonelGorev', 'AraUrunAdiNo')
            && $this->legacyHasColumn('tbAraUrun', 'No');
    }

    public function personnelTaskFullNameSql(): string
    {
        if (!$this->personnelTaskPersonnelJoinAvailable()) {
            return $this->legacyHasColumn('tbPersonelGorev', 'PersonelNo') ? 'CAST(pg.PersonelNo AS CHAR)' : "''";
        }

        $first = $this->legacyHasColumn('tbPersonel', 'Ad') ? 'p.Ad' : "''";
        $last = $this->legacyHasColumn('tbPersonel', 'Soyad') ? 'p.Soyad' : "''";

        return $this->legacyFullNameSql($first, $last);
    }

    public function personnelTaskTextSql(string $table, string $column, string $alias): string
    {
        return $this->legacyHasColumn($table, $column) ? "IFNULL({$alias}.{$column}, '')" : "''";
    }

    public function personnelTaskNumberSql(string $column): string
    {
        return $this->legacyHasColumn('tbPersonelGorev', $column) ? "COALESCE(pg.{$column}, 0)" : '0';
    }

    public function getDashboardStats(): array
    {
        $totalOrders = DB::table('tbSiparisSatir')->where('Aktif', 1)->count();
        $pending = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'UretimBekliyor')->count();
        $workOrderIssued = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'IsEmriVerildi')->count();
        $totalPersonnel = DB::table('tbPersonel')->count();
        $activeTasks = DB::table('tbPersonelGorev')
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->pendingApprovalSql());
            })
            ->count();
        $poolTasks = DB::table('tbBolumHavuz')->where('Adet', '>', 0)->count();

        return [
            'totalOrders' => $totalOrders,
            'pending' => $pending,
            'workOrderIssued' => $workOrderIssued,
            'totalPersonnel' => $totalPersonnel,
            'activeTasks' => $activeTasks,
            'poolTasks' => $poolTasks,
        ];
    }

    public function getLookups(): array
    {
        $departments = DB::table('tbBolum')->select('No as id', 'BolumAdi as name')->orderBy('BolumAdi')->get();
        $components = DB::table('tbAraUrun')->select('No as id', 'AraUrunAdi as name')->orderBy('AraUrunAdi')->get();
        $personnel = Personnel::query()
            ->select('PersonelNo', 'Ad', 'Soyad')
            ->orderBy('Ad')
            ->get()
            ->map(function ($person) {
                return [
                    'id' => $person->PersonelNo,
                    'name' => $person->Ad,
                    'surname' => $person->Soyad,
                ];
            });

        return [
            'departments' => $departments,
            'components' => $components,
            'personnel' => $personnel,
        ];
    }

    public function getChartData(string $primary, string $secondary): array
    {
        $labels = [];
        $data = [];

        if ($primary === 'departments') {
            $depts = DB::table('tbBolum')->orderBy('BolumAdi')->get();
            foreach ($depts as $d) {
                $labels[] = $d->BolumAdi;
                $count = DB::table('tbPersonelGorev')
                    ->where('BolumAdiNo', $d->No)
                    ->whereRaw($this->approvedApprovalSql())
                    ->sum('Adet');
                $data[] = intval($count);
            }
        } elseif ($primary === 'components') {
            $query = DB::table('tbPersonelGorev as pg')
                ->join('tbAraUrun as a', 'pg.AraUrunAdiNo', '=', 'a.No')
                ->whereRaw($this->approvedApprovalSql('pg.Onay'))
                ->select('a.AraUrunAdi', DB::raw('SUM(pg.Adet) as toplam'))
                ->groupBy('a.AraUrunAdi')
                ->orderByDesc('toplam');

            if ($secondary !== '0') {
                $query->where('pg.BolumAdiNo', $secondary);
            }

            $results = $query->limit(15)->get();
            foreach ($results as $r) {
                $labels[] = $r->AraUrunAdi;
                $data[] = intval($r->toplam);
            }
        } elseif ($primary === 'personnel') {
            $query = DB::table('tbPersonelGorev as pg')
                ->join('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
                ->whereRaw($this->approvedApprovalSql('pg.Onay'))
                ->select(DB::raw($this->legacyFullNameSql('p.Ad', 'p.Soyad') . " as PersonelAdi"), DB::raw('SUM(pg.Adet) as toplam'))
                ->groupBy('p.Ad', 'p.Soyad')
                ->orderByDesc('toplam');

            $results = $query->limit(15)->get();
            foreach ($results as $r) {
                $labels[] = $r->PersonelAdi;
                $data[] = intval($r->toplam);
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    public function getPersonnelTaskReport(array $filters, int $perPage, string $urlPath): array
    {
        if (!$this->legacyHasTable('tbPersonelGorev')) {
            return [
                'current_page' => 1,
                'data' => [],
                'first_page_url' => null,
                'from' => null,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $urlPath,
                'per_page' => $perPage,
                'prev_page_url' => null,
                'to' => null,
                'total' => 0,
                'total_adet' => 0,
                'total_bekleyen' => 0,
            ];
        }

        $query = $this->personnelTaskBaseQuery();

        if (!empty($filters['personnel_id']) && $this->legacyHasColumn('tbPersonelGorev', 'PersonelNo')) {
            $query->where('pg.PersonelNo', $filters['personnel_id']);
        }

        $this->applyPersonnelTaskSearch($query, $filters['search'] ?? '');
        $this->applyPersonnelTaskDateFilter($query, $filters['date_filter'] ?? '', $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        $summaryQuery = clone $query;
        $summary = $summaryQuery
            ->selectRaw('SUM(' . $this->personnelTaskNumberSql('Adet') . ') as total_adet, SUM(' . $this->personnelTaskNumberSql('BekleyenAdet') . ') as total_bekleyen')
            ->first();

        $this->applyPersonnelTaskSort($query, $filters['sort_by'] ?? 'pg.No', $filters['sort_dir'] ?? 'desc');
        $data = $this->selectPersonnelTaskColumns($query)->paginate($perPage);

        $payload = $data->toArray();
        $payload['total_adet'] = (int) ($summary->total_adet ?? 0);
        $payload['total_bekleyen'] = (int) ($summary->total_bekleyen ?? 0);

        foreach ($payload['data'] as $index => $row) {
            $row = (array) $row;
            $row['Durum'] = $this->personnelTaskStatus($row['Onay'] ?? null);
            $payload['data'][$index] = $row;
        }

        return $payload;
    }

    public function getRawPersonnelTasks(array $filters): \Illuminate\Support\Collection
    {
        if (!$this->legacyHasTable('tbPersonelGorev')) {
            return collect();
        }

        $query = $this->personnelTaskBaseQuery();

        if (!empty($filters['personnel_id']) && $this->legacyHasColumn('tbPersonelGorev', 'PersonelNo')) {
            $query->where('pg.PersonelNo', $filters['personnel_id']);
        }

        $this->applyPersonnelTaskSearch($query, $filters['search'] ?? '');
        $this->applyPersonnelTaskDateFilter($query, $filters['date_filter'] ?? '', $filters['start_date'] ?? null, $filters['end_date'] ?? null);
        $this->applyPersonnelTaskSort($query, $filters['sort_by'] ?? 'pg.No', $filters['sort_dir'] ?? 'desc');

        return $this->selectPersonnelTaskColumns($query)->get();
    }

    public function getTaskReport(array $filters, int $perPage): array
    {
        $query = DB::table('tbGorevler as gr')
            ->join('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
            ->join('tbPersonel as p', 'gr.PersonelNo', '=', 'p.PersonelNo')
            ->join('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
            ->join('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
            ->where('gr.PersonelNo', '>', 0)
            ->where('gr.ToplamAdet', '>', 0)
            ->select(
                'gr.No',
                'u.UrunID',
                DB::raw($this->legacyFullNameSql() . ' as TamAd'),
                'gr.GorevBaslamaTarihi',
                'gr.GorevBitisTarihi',
                'gr.ToplamAdet',
                'gr.Performans',
                'b.BolumAdi',
                'au.AraUrunAdi'
            );

        if (!empty($filters['product_id'])) {
            $query->where('gr.UrunIDNo', $filters['product_id']);
        }

        if (!empty($filters['personnel_id'])) {
            $query->where('gr.PersonelNo', $filters['personnel_id']);
        }

        $this->applyTaskReportSearch($query, $filters['search'] ?? '');
        $this->applyTaskReportDateFilter($query, $filters['date_filter'] ?? '', $filters['start_date'] ?? null, $filters['end_date'] ?? null);
        
        $this->applyTaskReportSort($query, $filters['sort_by'] ?? 'gr.No', $filters['sort_dir'] ?? 'desc', [
            'gr.No' => 'gr.No',
            'No' => 'gr.No',
            'p.Ad' => 'p.Ad',
            'UrunID' => 'u.UrunID',
            'u.UrunID' => 'u.UrunID',
            'TamAd' => 'TamAd',
            'Personel' => 'TamAd',
            'GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'gr.GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'GorevBitisTarihi' => 'gr.GorevBitisTarihi',
            'gr.GorevBitisTarihi' => 'gr.GorevBitisTarihi',
            'ToplamAdet' => 'gr.ToplamAdet',
            'gr.ToplamAdet' => 'gr.ToplamAdet',
            'Performans' => 'gr.Performans',
            'gr.Performans' => 'gr.Performans',
            'BolumAdi' => 'b.BolumAdi',
            'b.BolumAdi' => 'b.BolumAdi',
            'AraUrunAdi' => 'au.AraUrunAdi',
            'au.AraUrunAdi' => 'au.AraUrunAdi',
        ]);

        return $query->paginate($perPage)->toArray();
    }

    public function getRawTasksForExport(array $filters): \Illuminate\Support\Collection
    {
        $query = DB::table('tbGorevler as gr')
            ->join('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
            ->join('tbPersonel as p', 'gr.PersonelNo', '=', 'p.PersonelNo')
            ->join('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
            ->join('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
            ->where('gr.PersonelNo', '>', 0)
            ->where('gr.ToplamAdet', '>', 0)
            ->select(
                'gr.No',
                DB::raw($this->legacyFullNameSql() . ' as Personel'),
                'u.UrunID',
                'au.AraUrunAdi',
                'gr.GorevBaslamaTarihi',
                'gr.GorevBitisTarihi',
                'gr.Performans',
                'gr.ToplamAdet',
                'b.BolumAdi'
            );

        if (!empty($filters['product_id'])) {
            $query->where('gr.UrunIDNo', $filters['product_id']);
        }
        if (!empty($filters['personnel_id'])) {
            $query->where('gr.PersonelNo', $filters['personnel_id']);
        }

        $this->applyTaskReportSearch($query, $filters['search'] ?? '');
        $this->applyTaskReportDateFilter($query, $filters['date_filter'] ?? '', $filters['start_date'] ?? null, $filters['end_date'] ?? null);
        
        $this->applyTaskReportSort($query, $filters['sort_by'] ?? 'gr.No', $filters['sort_dir'] ?? 'desc', [
            'gr.No' => 'gr.No',
            'No' => 'gr.No',
            'p.Ad' => 'p.Ad',
            'UrunID' => 'u.UrunID',
            'u.UrunID' => 'u.UrunID',
            'TamAd' => 'Personel',
            'Personel' => 'Personel',
            'GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'gr.GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'GorevBitisTarihi' => 'gr.GorevBitisTarihi',
            'gr.GorevBitisTarihi' => 'gr.GorevBitisTarihi',
            'ToplamAdet' => 'gr.ToplamAdet',
            'Adet' => 'gr.ToplamAdet',
            'gr.ToplamAdet' => 'gr.ToplamAdet',
            'Performans' => 'gr.Performans',
            'gr.Performans' => 'gr.Performans',
            'BolumAdi' => 'b.BolumAdi',
            'b.BolumAdi' => 'b.BolumAdi',
            'AraUrunAdi' => 'au.AraUrunAdi',
            'au.AraUrunAdi' => 'au.AraUrunAdi',
        ]);

        return $query->get();
    }

    public function getPerformanceReport(): \Illuminate\Support\Collection
    {
        return DB::table('tbPersonel as p')
            ->join('tbGorevler as gr', 'p.PersonelNo', '=', 'gr.PersonelNo')
            ->where('gr.PersonelNo', '>', 0)
            ->where('gr.ToplamAdet', '>', 0)
            ->select(
                DB::raw($this->legacyFullNameSql('p.Ad', 'p.Soyad') . " as PersonelAdi"),
                DB::raw('SUM(gr.Performans) as ToplamPerformansScore'),
                DB::raw('AVG(gr.Performans) as OrtalamaPerformans'),
                DB::raw('COUNT(gr.No) as ToplamGorevSayisi')
            )
            ->groupBy('p.Ad', 'p.Soyad')
            ->orderByDesc('ToplamPerformansScore')
            ->get();
    }

    // ── Helper functions for query filters & sorting ──

    private function personnelTaskBaseQuery()
    {
        $query = DB::table('tbPersonelGorev as pg');

        if ($this->personnelTaskProductJoinAvailable()) {
            $query->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No');
        }

        if ($this->personnelTaskPersonnelJoinAvailable()) {
            $query->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo');
        }

        if ($this->personnelTaskDepartmentJoinAvailable()) {
            $query->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No');
        }

        if ($this->personnelTaskComponentJoinAvailable()) {
            $query->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No');
        }

        return $query;
    }

    private function applyPersonnelTaskDateFilter($query, string $dateFilter, ?string $start, ?string $end): void
    {
        if (empty($dateFilter) || $dateFilter === 'hepsi') {
            return;
        }

        $dateColumn = $this->personnelTaskDateColumn();
        if (!$dateColumn) {
            return;
        }

        $now = now();
        $reportDateSql = 'DATE(' . $this->legacyDateSql('pg.' . $dateColumn) . ')';

        if ($dateFilter === 'gun') {
            $query->whereRaw("{$reportDateSql} = ?", [$now->toDateString()]);
        } elseif ($dateFilter === 'hafta') {
            $query->whereRaw("{$reportDateSql} BETWEEN ? AND ?", [
                $now->copy()->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString(),
            ]);
        } elseif ($dateFilter === 'ay') {
            $query->whereRaw("MONTH({$reportDateSql}) = ? AND YEAR({$reportDateSql}) = ?", [
                $now->month,
                $now->year,
            ]);
        } elseif ($dateFilter === '6ay') {
            $query->whereRaw("{$reportDateSql} BETWEEN ? AND ?", [
                $now->copy()->subMonths(6)->toDateString(),
                $now->toDateString(),
            ]);
        } elseif ($dateFilter === 'yil') {
            $query->whereRaw("{$reportDateSql} >= ?", [$now->copy()->subYear()->toDateString()]);
        } elseif ($dateFilter === 'tarih') {
            if ($start) {
                $query->whereRaw("{$reportDateSql} >= ?", [$start]);
            }
            if ($end) {
                $query->whereRaw("{$reportDateSql} <= ?", [$end]);
            }
        }
    }

    private function applyPersonnelTaskSearch($query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';
        $conditions = [];
        $rawConditions = [];

        if ($this->personnelTaskProductJoinAvailable() && $this->legacyHasColumn('tbUrunler', 'UrunID')) {
            $conditions[] = ['u.UrunID', 'like', $like];
        }
        if ($this->personnelTaskComponentJoinAvailable() && $this->legacyHasColumn('tbAraUrun', 'AraUrunAdi')) {
            $conditions[] = ['au.AraUrunAdi', 'like', $like];
        }
        if ($this->personnelTaskDepartmentJoinAvailable() && $this->legacyHasColumn('tbBolum', 'BolumAdi')) {
            $conditions[] = ['b.BolumAdi', 'like', $like];
        }
        if ($dateColumn = $this->personnelTaskDateColumn()) {
            $conditions[] = ['pg.' . $dateColumn, 'like', $like];
        }
        if ($this->personnelTaskPersonnelJoinAvailable()) {
            $rawConditions[] = [$this->personnelTaskFullNameSql() . ' LIKE ?', [$like]];
        }
        if (ctype_digit($search) && $this->legacyHasColumn('tbPersonelGorev', 'No')) {
            $conditions[] = ['pg.No', '=', intval($search)];
        }

        if (empty($conditions) && empty($rawConditions)) {
            return;
        }

        $query->where(function ($q) use ($conditions, $rawConditions) {
            $first = true;
            foreach ($conditions as [$column, $operator, $value]) {
                $method = $first ? 'where' : 'orWhere';
                $q->{$method}($column, $operator, $value);
                $first = false;
            }
            foreach ($rawConditions as [$sql, $bindings]) {
                $method = $first ? 'whereRaw' : 'orWhereRaw';
                $q->{$method}($sql, $bindings);
                $first = false;
            }
        });
    }

    private function applyPersonnelTaskSort($query, string $sortKey, string $sortDir): void
    {
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $dateColumn = $this->personnelTaskDateColumn();
        if ($dateColumn && in_array($sortKey, ['gr.GorevBaslamaTarihi', 'pg.GorevBaslamaTarihi', 'GorevBaslamaTarihi', 'pg.' . $dateColumn, $dateColumn], true)) {
            $query->orderByRaw($this->legacyDateSql('pg.' . $dateColumn) . ' ' . $sortDir);
            if ($this->legacyHasColumn('tbPersonelGorev', 'No')) {
                $query->orderBy('pg.No', 'desc');
            }
            return;
        }

        if ($this->personnelTaskPersonnelJoinAvailable() && in_array($sortKey, ['p.Ad', 'TamAd', 'Personel'], true)) {
            if ($this->legacyHasColumn('tbPersonel', 'Ad')) {
                $query->orderBy('p.Ad', $sortDir);
            }
            if ($this->legacyHasColumn('tbPersonel', 'Soyad')) {
                $query->orderBy('p.Soyad', $sortDir);
            }
            if ($this->legacyHasColumn('tbPersonelGorev', 'No')) {
                $query->orderBy('pg.No', 'desc');
            }
            return;
        }

        $map = [];
        if ($this->legacyHasColumn('tbPersonelGorev', 'No')) {
            $map['gr.No'] = 'pg.No';
            $map['pg.No'] = 'pg.No';
            $map['No'] = 'pg.No';
        }
        if ($this->personnelTaskProductJoinAvailable() && $this->legacyHasColumn('tbUrunler', 'UrunID')) {
            $map['UrunID'] = 'u.UrunID';
            $map['u.UrunID'] = 'u.UrunID';
        }
        if ($this->personnelTaskComponentJoinAvailable() && $this->legacyHasColumn('tbAraUrun', 'AraUrunAdi')) {
            $map['AraUrunAdi'] = 'au.AraUrunAdi';
            $map['au.AraUrunAdi'] = 'au.AraUrunAdi';
        }
        if ($this->personnelTaskDepartmentJoinAvailable() && $this->legacyHasColumn('tbBolum', 'BolumAdi')) {
            $map['BolumAdi'] = 'b.BolumAdi';
            $map['b.BolumAdi'] = 'b.BolumAdi';
        }
        if ($this->legacyHasColumn('tbPersonelGorev', 'Adet')) {
            $map['Adet'] = 'pg.Adet';
            $map['pg.Adet'] = 'pg.Adet';
            $map['gr.ToplamAdet'] = 'pg.Adet';
        }
        if ($this->legacyHasColumn('tbPersonelGorev', 'BekleyenAdet')) {
            $map['BekleyenAdet'] = 'pg.BekleyenAdet';
            $map['pg.BekleyenAdet'] = 'pg.BekleyenAdet';
        }

        $sortBy = $map[$sortKey] ?? ($map['pg.No'] ?? null);
        if ($sortBy) {
            $query->orderBy($sortBy, $sortDir);
        }

        if ($sortBy !== 'pg.No' && $this->legacyHasColumn('tbPersonelGorev', 'No')) {
            $query->orderBy('pg.No', 'desc');
        }
    }

    private function selectPersonnelTaskColumns($query)
    {
        $dateColumn = $this->personnelTaskDateColumn();
        $dateSql = $dateColumn ? "IFNULL(pg.{$dateColumn}, '')" : "''";
        $productSql = $this->personnelTaskProductJoinAvailable()
            ? $this->personnelTaskTextSql('tbUrunler', 'UrunID', 'u')
            : "''";
        $componentSql = $this->personnelTaskComponentJoinAvailable()
            ? $this->personnelTaskTextSql('tbAraUrun', 'AraUrunAdi', 'au')
            : "''";
        $departmentSql = $this->personnelTaskDepartmentJoinAvailable()
            ? $this->personnelTaskTextSql('tbBolum', 'BolumAdi', 'b')
            : "''";
        $onaySql = $this->legacyHasColumn('tbPersonelGorev', 'Onay') ? "IFNULL(pg.Onay, '')" : "''";
        $noSql = $this->legacyHasColumn('tbPersonelGorev', 'No') ? 'pg.No' : '0';

        return $query->select(
            DB::raw($noSql . ' as No'),
            DB::raw($this->personnelTaskFullNameSql() . ' as TamAd'),
            DB::raw($productSql . ' as UrunID'),
            DB::raw($componentSql . ' as AraUrunAdi'),
            DB::raw($dateSql . ' as GorevBaslamaTarihi'),
            DB::raw($departmentSql . ' as BolumAdi'),
            DB::raw($this->personnelTaskNumberSql('Adet') . ' as Adet'),
            DB::raw($this->personnelTaskNumberSql('BekleyenAdet') . ' as BekleyenAdet'),
            DB::raw($onaySql . ' as Onay')
        );
    }

    private function personnelTaskStatus(?string $approval): string
    {
        $value = strtolower(trim((string) $approval));

        return in_array($value, ['1', 'true', 'evet', 'yes'], true) ? 'Üretim Dışı' : 'Üretimde';
    }

    private function applyTaskReportDateFilter($query, string $dateFilter, ?string $start, ?string $end): void
    {
        if (empty($dateFilter) || $dateFilter === 'hepsi') {
            return;
        }

        $now = now();
        $reportDateSql = 'DATE(' . $this->legacyDateSql('gr.GorevBitisTarihi') . ')';

        if ($dateFilter === 'gun') {
            $query->whereRaw("{$reportDateSql} = ?", [$now->toDateString()]);
        } elseif ($dateFilter === 'hafta') {
            $query->whereRaw("{$reportDateSql} BETWEEN ? AND ?", [
                $now->copy()->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString(),
            ]);
        } elseif ($dateFilter === 'ay') {
            $query->whereRaw("MONTH({$reportDateSql}) = ? AND YEAR({$reportDateSql}) = ?", [
                $now->month,
                $now->year,
            ]);
        } elseif ($dateFilter === '6ay') {
            $query->whereRaw("{$reportDateSql} BETWEEN ? AND ?", [
                $now->copy()->subMonths(6)->toDateString(),
                $now->toDateString(),
            ]);
        } elseif ($dateFilter === 'yil') {
            $query->whereRaw("{$reportDateSql} >= ?", [$now->copy()->subYear()->toDateString()]);
        } elseif ($dateFilter === 'tarih') {
            if ($start) {
                $query->whereRaw("{$reportDateSql} >= ?", [$start]);
            }
            if ($end) {
                $query->whereRaw("{$reportDateSql} <= ?", [$end]);
            }
        }
    }

    private function applyTaskReportSearch($query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';
        $query->where(function ($q) use ($like) {
            $q->where('gr.No', 'like', $like)
                ->orWhere('u.UrunID', 'like', $like)
                ->orWhere('au.AraUrunAdi', 'like', $like)
                ->orWhere('b.BolumAdi', 'like', $like)
                ->orWhere('gr.GorevBaslamaTarihi', 'like', $like)
                ->orWhere('gr.GorevBitisTarihi', 'like', $like)
                ->orWhereRaw($this->legacyFullNameSql() . ' LIKE ?', [$like]);
        });
    }

    private function applyTaskReportSort($query, string $sortKey, string $sortDir, array $map): void
    {
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $dateSortColumns = [
            'gr.GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'GorevBaslamaTarihi' => 'gr.GorevBaslamaTarihi',
            'gr.GorevBitisTarihi' => 'gr.GorevBitisTarihi',
            'GorevBitisTarihi' => 'gr.GorevBitisTarihi',
        ];

        if (isset($dateSortColumns[$sortKey])) {
            $query->orderByRaw($this->legacyDateSql($dateSortColumns[$sortKey]) . ' ' . $sortDir)
                ->orderBy('gr.No', 'desc');
            return;
        }

        if (in_array($sortKey, ['p.Ad', 'TamAd', 'Personel'], true)) {
            $query->orderBy('p.Ad', $sortDir)
                ->orderBy('p.Soyad', $sortDir)
                ->orderBy('gr.No', 'desc');
            return;
        }

        $sortBy = $map[$sortKey] ?? $map['gr.No'];
        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'gr.No') {
            $query->orderBy('gr.No', 'desc');
        }
    }

    // ── Excel workbook generator logic ──

    public function buildPersonnelTasksWorkbook($tasks): string
    {
        $rows = [[
            'Personel Adı',
            'Ürün Adı',
            'Ara Ürün',
            'Görev Başlangıç Tarihi',
            'Toplam Adet',
            'Bekleyen Adet',
        ]];

        foreach ($tasks as $row) {
            $rows[] = [
                (string) ($row->TamAd ?? ''),
                (string) ($row->UrunID ?? ''),
                (string) ($row->AraUrunAdi ?? ''),
                (string) ($row->GorevBaslamaTarihi ?? ''),
                (int) ($row->Adet ?? 0),
                (int) ($row->BekleyenAdet ?? 0),
            ];
        }

        return $this->buildSimpleXlsxWorkbook('Görevler', $rows, [24, 44, 50, 22, 12, 14]);
    }

    public function buildSimpleXlsxWorkbook(string $sheetName, array $rows, array $columnWidths): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Sunucuda Excel dosyası oluşturmak için ZipArchive eklentisi gerekli.');
        }

        $rows = array_values($rows);
        if (count($rows) === 1) {
            $rows[] = array_fill(0, count($rows[0]), null);
        }

        $columnCount = max(1, ...array_map(fn ($row) => max(1, count($row)), $rows));
        foreach ($rows as $index => $row) {
            $rows[$index] = array_pad(array_values($row), $columnCount, null);
        }

        $sheetName = $this->safeExcelSheetName($sheetName);
        $lastColumn = $this->excelColumnName($columnCount);
        $lastRow = count($rows);
        $sheetRef = 'A1:' . $lastColumn . $lastRow;

        $tmp = tempnam(sys_get_temp_dir(), 'reports_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Excel dosyası için geçici alan oluşturulamadı.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Excel dosyası oluşturulamadı.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildReportXlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildReportXlsxRootRelsXml());
        $zip->addFromString('docProps/core.xml', $this->buildReportXlsxCoreXml());
        $zip->addFromString('docProps/app.xml', $this->buildReportXlsxAppXml($sheetName));
        $zip->addFromString('xl/workbook.xml', $this->buildReportXlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildReportXlsxWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildReportXlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildReportXlsxWorksheetXml($rows, $columnWidths, $sheetRef));
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        if ($content === false) {
            throw new \RuntimeException('Excel dosyası okunamadı.');
        }

        return $content;
    }

    private function buildReportXlsxWorksheetXml(array $rows, array $columnWidths, string $sheetRef): string
    {
        $columnCount = count($rows[0]);
        $columnsXml = '';
        for ($i = 1; $i <= $columnCount; $i++) {
            $width = $columnWidths[$i - 1] ?? 18;
            $columnsXml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
        }

        $sheetDataXml = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $rowAttributes = ' r="' . $rowNumber . '" spans="1:' . $columnCount . '"';
            if ($rowIndex === 0) {
                $rowAttributes .= ' ht="43.2" customHeight="1"';
            }

            $cellsXml = '';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->excelColumnName($columnIndex + 1) . $rowNumber;
                $style = $rowIndex === 0 ? 1 : ((is_int($value) || is_float($value)) ? 3 : 2);
                $cellsXml .= $this->buildReportXlsxCellXml($cellRef, $value, $style);
            }

            $sheetDataXml .= '<row' . $rowAttributes . '>' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $sheetRef . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="14.4"/>'
            . '<cols>' . $columnsXml . '</cols>'
            . '<sheetData>' . $sheetDataXml . '</sheetData>'
            . '<pageMargins left="0.25" right="0.7086614173228347" top="0.13" bottom="0.29" header="0.3149606299212598" footer="0.3149606299212598"/>'
            . '<pageSetup paperSize="9" orientation="landscape"/>'
            . '</worksheet>';
    }

    private function buildReportXlsxCellXml(string $cellRef, mixed $value, int $style): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $this->xmlEscape((string) $value) . '</t></is></c>';
    }

    private function buildReportXlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function buildReportXlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function buildReportXlsxWorkbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="23426"/>'
            . '<workbookPr defaultThemeVersion="164011"/>'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function buildReportXlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function buildReportXlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4B3621"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right><top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    private function buildReportXlsxCoreXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>zemuretim</dc:creator>'
            . '<cp:lastModifiedBy>zemuretim</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function buildReportXlsxAppXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>zemuretim</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xmlEscape($sheetName) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    private function safeExcelSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $name) ?: 'Sayfa1';
        $name = trim($name);

        return mb_substr($name === '' ? 'Sayfa1' : $name, 0, 31);
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
