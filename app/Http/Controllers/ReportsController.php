<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Personnel;

class ReportsController extends Controller
{
    private function pendingApprovalSql(string $column = 'Onay'): string
    {
        return "({$column} IS NULL OR {$column} = 0 OR {$column} = '0' OR LOWER(TRIM(CAST({$column} AS CHAR))) = 'false')";
    }

    private function approvedApprovalSql(string $column = 'Onay'): string
    {
        return "({$column} = 1 OR {$column} = '1' OR LOWER(TRIM(CAST({$column} AS CHAR))) = 'true')";
    }

    public function dashboardStats()
    {
        $totalOrders = DB::table('tbSiparisSatir')->where('Aktif', 1)->count();
        $pending = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'UretimBekliyor')->count();
        $workOrderIssued = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'IsEmriVerildi')->count();
        $totalPersonnel = DB::table('tbPersonel')->count();
        $activeTasks = DB::table('tbPersonelGorev')->whereRaw($this->pendingApprovalSql())->count();
        $poolTasks = DB::table('tbBolumHavuz')->where('Adet', '>', 0)->count();

        return response()->json([
            'totalOrders' => $totalOrders,
            'pending' => $pending,
            'workOrderIssued' => $workOrderIssued,
            'totalPersonnel' => $totalPersonnel,
            'activeTasks' => $activeTasks,
            'poolTasks' => $poolTasks,
        ]);
    }

    public function lookups()
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

        return response()->json([
            'departments' => $departments,
            'components' => $components,
            'personnel' => $personnel,
        ]);
    }

    public function chartData(Request $request)
    {
        $primary = $request->input('primary', 'departments');
        $secondary = $request->input('secondary', '0');

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
                ->select(DB::raw("CONCAT(p.Ad,' ',p.Soyad) as PersonelAdi"), DB::raw('SUM(pg.Adet) as toplam'))
                ->groupBy('p.Ad', 'p.Soyad')
                ->orderByDesc('toplam');

            $results = $query->limit(15)->get();
            foreach ($results as $r) {
                $labels[] = $r->PersonelAdi;
                $data[] = intval($r->toplam);
            }
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    // ==========================================
    // Görev Raporları API (Admin)
    // ==========================================
    public function getTaskReport(Request $request)
    {
        // ... (existing code below)
        $query = DB::table('tbGorevler as gr')
            ->join('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
            ->join('tbPersonel as p', 'gr.PersonelNo', '=', 'p.PersonelNo')
            ->join('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
            ->join('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
            ->select(
                'gr.No',
                'u.UrunID',
                DB::raw("CONCAT(p.Ad, ' ', p.Soyad) as TamAd"),
                'gr.GorevBaslamaTarihi',
                'gr.GorevBitisTarihi',
                'gr.ToplamAdet',
                'gr.Performans',
                'b.BolumAdi',
                'au.AraUrunAdi'
            );

        if ($request->filled('product_id')) {
            $query->where('gr.UrunIDNo', $request->input('product_id'));
        }

        if ($request->filled('personnel_id')) {
            $query->where('gr.PersonelNo', $request->input('personnel_id'));
        }

        // Date Filtering
        $dateFilter = $request->input('date_filter');
        if ($dateFilter && $dateFilter !== 'hepsi') {
            $now = now();
            if ($dateFilter === 'gun') {
                $query->whereDate('gr.GorevBitisTarihi', $now->toDateString()); // Assuming formatting is parsable by MySQL/Laravel
            } elseif ($dateFilter === 'hafta') {
                $query->whereBetween('gr.GorevBitisTarihi', [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()]);
            } elseif ($dateFilter === 'ay') {
                $query->whereMonth('gr.GorevBitisTarihi', $now->month)->whereYear('gr.GorevBitisTarihi', $now->year);
            } elseif ($dateFilter === '6ay') {
                $query->whereBetween('gr.GorevBitisTarihi', [$now->copy()->subMonths(6)->toDateString(), $now->toDateString()]);
            } elseif ($dateFilter === 'yil') {
                $query->whereDate('gr.GorevBitisTarihi', '>=', $now->copy()->subYear()->toDateString());
            } elseif ($dateFilter === 'tarih') {
                $start = $request->input('start_date');
                $end = $request->input('end_date');
                if ($start) $query->whereDate('gr.GorevBitisTarihi', '>=', $start);
                if ($end) $query->whereDate('gr.GorevBitisTarihi', '<=', $end);
            }
        }
        
        $sortBy = $request->input('sort_by', 'gr.No');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }
    
    public function exportExcelTasks(Request $request)
    {
        // Replicate logic just for raw query
        $query = DB::table('tbGorevler as gr')
            ->join('tbUrunler as u', 'gr.UrunIDNo', '=', 'u.No')
            ->join('tbPersonel as p', 'gr.PersonelNo', '=', 'p.PersonelNo')
            ->join('tbBolum as b', 'gr.BolumAdiNo', '=', 'b.No')
            ->join('tbAraUrun as au', 'gr.AraUrunAdiNo', '=', 'au.No')
            ->select(
                'gr.No',
                DB::raw("CONCAT(p.Ad, ' ', p.Soyad) as Personel"),
                'u.UrunID',
                'au.AraUrunAdi',
                'gr.GorevBaslamaTarihi',
                'gr.GorevBitisTarihi',
                'gr.Performans',
                'gr.ToplamAdet',
                'b.BolumAdi'
            );

        if ($request->filled('product_id')) {
            $query->where('gr.UrunIDNo', $request->input('product_id'));
        }
        if ($request->filled('personnel_id')) {
            $query->where('gr.PersonelNo', $request->input('personnel_id'));
        }

        $dateFilter = $request->input('date_filter');
        if ($dateFilter && $dateFilter !== 'hepsi') {
            $now = now();
            if ($dateFilter === 'gun') {
                $query->whereDate('gr.GorevBitisTarihi', $now->toDateString());
            } elseif ($dateFilter === 'hafta') {
                $query->whereBetween('gr.GorevBitisTarihi', [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()]);
            } elseif ($dateFilter === 'ay') {
                $query->whereMonth('gr.GorevBitisTarihi', $now->month)->whereYear('gr.GorevBitisTarihi', $now->year);
            } elseif ($dateFilter === '6ay') {
                $query->whereBetween('gr.GorevBitisTarihi', [$now->copy()->subMonths(6)->toDateString(), $now->toDateString()]);
            } elseif ($dateFilter === 'yil') {
                $query->whereDate('gr.GorevBitisTarihi', '>=', $now->copy()->subYear()->toDateString());
            } elseif ($dateFilter === 'tarih') {
                $start = $request->input('start_date');
                $end = $request->input('end_date');
                if ($start) $query->whereDate('gr.GorevBitisTarihi', '>=', $start);
                if ($end) $query->whereDate('gr.GorevBitisTarihi', '<=', $end);
            }
        }
        
        $sortBy = $request->input('sort_by', 'gr.No');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $tasks = $query->get();

        $csvFileName = 'PersonelRapor_' . date('Ymd_His') . '.csv';

        // Set response headers for direct download
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Output CSV stream
        $callback = function() use($tasks) {
            $file = fopen('php://output', 'w');
            // Write BOM for Excel UTF-8 support
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, ['No', 'Personel', 'UrunID', 'AraUrunAdi', 'GorevBaslamaTarihi', 'GorevBitisTarihi', 'Performans', 'Adet', 'BolumAdi']);

            foreach ($tasks as $row) {
                fputcsv($file, [
                    $row->No,
                    $row->Personel,
                    $row->UrunID,
                    $row->AraUrunAdi,
                    $row->GorevBaslamaTarihi,
                    $row->GorevBitisTarihi,
                    $row->Performans,
                    $row->ToplamAdet,
                    $row->BolumAdi
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getPerformanceReport(Request $request)
    {
        $query = DB::table('tbPersonel as p')
            ->join('tbGorevler as gr', 'p.PersonelNo', '=', 'gr.PersonelNo')
            ->select(
                DB::raw("CONCAT(p.Ad, ' ', p.Soyad) as PersonelAdi"),
                DB::raw('SUM(gr.Performans) as ToplamPerformansScore'),
                DB::raw('AVG(gr.Performans) as OrtalamaPerformans'),
                DB::raw('COUNT(gr.No) as ToplamGorevSayisi')
            )
            ->groupBy('p.Ad', 'p.Soyad')
            ->orderByDesc('ToplamPerformansScore');

        $data = $query->get();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
