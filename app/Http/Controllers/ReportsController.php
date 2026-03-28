<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ReportsController extends Controller
{
    public function dashboardStats()
    {
        $totalOrders = DB::table('tbSiparisSatir')->where('Aktif', 1)->count();
        $pending = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'UretimBekliyor')->count();
        $workOrderIssued = DB::table('tbSiparisSatir')->where('Aktif', 1)->where('Durum', 'IsEmriVerildi')->count();
        $totalPersonnel = DB::table('tbPersonel')->count();
        $activeTasks = DB::table('tbPersonelGorev')->where('Onay', 0)->count();
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
        $personnel = User::select('id', 'name', 'surname')->orderBy('name')->get();

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
                    ->where('Onay', 1)
                    ->sum('Adet');
                $data[] = intval($count);
            }
        } elseif ($primary === 'components') {
            $query = DB::table('tbPersonelGorev as pg')
                ->join('tbAraUrun as a', 'pg.AraUrunAdiNo', '=', 'a.No')
                ->where('pg.Onay', 1)
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
                ->where('pg.Onay', 1)
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
}
