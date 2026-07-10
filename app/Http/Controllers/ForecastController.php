<?php

namespace App\Http\Controllers;

use App\Models\DemandForecastSnapshot;
use App\Models\Product;
use App\Services\ForecastService;
use App\Services\ForecastTrainingDataBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ForecastController extends Controller
{
    public function __construct(
        protected ForecastService $forecastService,
        protected ForecastTrainingDataBuilder $trainingDataBuilder
    ) {}

    /**
     * Get list of products with their latest predictions.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $periodType = $request->query('period_type', 'monthly');

        $query = DB::table('demand_forecast_snapshots as dfs')
            ->join('tbUrunler as u', 'dfs.product_id', '=', 'u.No')
            ->select('dfs.*', 'u.UrunID as product_name')
            ->where('dfs.period_type', $periodType);

        if ($status) {
            $query->where('dfs.status', $status);
        }

        // Get only latest forecast per product
        $query->whereIn('dfs.id', function($q) {
            $q->selectRaw('MAX(id)')
                ->from('demand_forecast_snapshots')
                ->groupBy('product_id', 'period_type');
        });

        $forecasts = $query->orderByDesc('dfs.period_date')->get();

        return response()->json([
            'success' => true,
            'forecasts' => $forecasts,
        ]);
    }

    /**
     * Get detailed prediction and demand history for a product.
     */
    public function detail(Request $request, $productId)
    {
        $productId = intval($productId);
        $periodType = $request->query('period_type', 'monthly');

        $productName = DB::table('tbUrunler')->where('No', $productId)->value('UrunID');
        if (!$productName) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
        }

        // Fetch historical demand data
        $history = $this->trainingDataBuilder->getHistory('urun', $productId, $periodType, 24);

        // Fetch future predictions
        $predictions = DemandForecastSnapshot::where('product_id', $productId)
            ->where('period_type', $periodType)
            ->orderBy('period_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'product_name' => $productName,
            'history' => $history,
            'predictions' => $predictions,
        ]);
    }

    /**
     * Approve a predicted forecast snapshot.
     */
    public function approve(Request $request, $id)
    {
        $snapshot = DemandForecastSnapshot::findOrFail($id);
        
        $snapshot->update([
            'status' => 'approved',
            'approved_by' => Auth::user()?->personnel_no ?? Auth::user()?->PersonelNo ?? Auth::user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tahmin onaylandı ve üretime delege edildi.',
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Override/edit a predicted forecast snapshot value.
     */
    public function override(Request $request, $id)
    {
        $request->validate([
            'override_value' => 'required|integer|min:0',
        ]);

        $snapshot = DemandForecastSnapshot::findOrFail($id);
        
        $snapshot->update([
            'status' => 'overridden',
            'override_value' => intval($request->input('override_value')),
            'approved_by' => Auth::user()?->personnel_no ?? Auth::user()?->PersonelNo ?? Auth::user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tahmin değeri elle güncellendi (Override).',
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Trigger forecasting calculation manually.
     */
    public function runForecast(Request $request)
    {
        $productId = $request->input('product_id');
        $periodType = $request->input('period_type', 'monthly');

        if ($productId) {
            $result = $this->forecastService->forecastProduct(intval($productId), $periodType);
            return response()->json($result);
        }

        // Trigger in background via Artisan call
        try {
            \Illuminate\Support\Facades\Artisan::queue('forecast:run', [
                '--weekly' => $periodType === 'weekly',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Tahmin modeli arka planda çalıştırılmak üzere sıraya alındı.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Model tetiklenirken hata oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Render the forecasting dashboard view.
     */
    public function dashboardView()
    {
        return view('forecast.dashboard');
    }
}
