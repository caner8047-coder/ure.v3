<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index');
    }

    public function orders()
    {
        return view('orders.index');
    }

    public function special()
    {
        return view('orders.special');
    }

    public function criticalStocks()
    {
        return view('stocks.critical');
    }

    public function productMatch()
    {
        return view('products.match');
    }

    public function productSettings()
    {
        return view('products.settings');
    }

    public function stocks()
    {
        return view('stocks.index');
    }

    public function statistics()
    {
        return view('reports.statistics');
    }

    public function database()
    {
        return view('admin.database');
    }

    public function getMessages(Request $request)
    {
        $query = DB::table('tbIletisim as m')
            ->leftJoin('tbPersonel as p', 'm.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'm.BolumAdiNo', '=', 'b.No')
            ->select(
                'm.MesajNo', 
                'm.Mesaj', 
                'm.Tarih', 
                'm.Saat',
                DB::raw("IFNULL(b.BolumAdi, 'Genel') as BolumAdi"),
                DB::raw("IFNULL(CONCAT(p.Ad, ' ', p.Soyad), 'Sistem') as Gonderen")
            );

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('m.Mesaj', 'like', "%{$search}%")
                  ->orWhere('p.Ad', 'like', "%{$search}%")
                  ->orWhere('p.Soyad', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('m.Tarih')->orderByDesc('m.Saat');

        $perPage = $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    public function deleteMessage($id)
    {
        $deleted = DB::table('tbIletisim')->where('MesajNo', $id)->delete();
        
        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Mesaj başarıyla silindi.']);
        }
        
        return response()->json(['success' => false, 'message' => 'Mesaj bulunamadı!'], 404);
    }
}
