<?php

namespace App\Services;

use App\Models\DemandForecastSnapshot;
use App\Events\ForecastCompleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ForecastService
{
    public function __construct(
        protected ForecastTrainingDataBuilder $trainingDataBuilder,
        protected AIBrainService $aiBrain,
        protected AppSettingService $settings
    ) {}

    /**
     * Generate demand forecast for a single product.
     */
    public function forecastProduct(int $productId, string $period = 'monthly', int $periods = 6): array
    {
        $history = $period === 'weekly'
            ? $this->trainingDataBuilder->getWeeklyProductHistory($productId)
            : $this->trainingDataBuilder->getMonthlyProductHistory($productId);

        if (empty($history)) {
            return [
                'success' => false,
                'message' => 'Yetersiz geçmiş sipariş verisi (En az 1 sipariş kaydı gerekli).',
            ];
        }

        // Get Python Microservice URL
        $forecastHost = env('FORECAST_SERVICE_HOST', 'forecast');
        $forecastPort = env('FORECAST_SERVICE_PORT', '5000');
        $url = "http://{$forecastHost}:{$forecastPort}/predict";

        try {
            Log::info("Sending forecast request to Python service for product #{$productId}");
            $response = Http::timeout(30)->post($url, [
                'history' => $history,
                'periods' => $periods,
                'freq' => $period === 'weekly' ? 'W' : 'M',
            ]);

            if (!$response->successful()) {
                Log::warning("Prophet forecast service returned status {$response->status()}");
                return [
                    'success' => false,
                    'message' => 'Tahmin mikroservisinden hata yanıtı alındı.',
                ];
            }

            $data = $response->json();
            if (empty($data['predictions'])) {
                return [
                    'success' => false,
                    'message' => 'Tahmin verisi üretilemedi.',
                ];
            }

            $savedSnapshots = [];
            foreach ($data['predictions'] as $prediction) {
                // Delete existing prediction for same product, date and period to avoid duplicates
                DB::table('demand_forecast_snapshots')
                    ->where('product_id', $productId)
                    ->where('period_date', $prediction['ds'])
                    ->where('period_type', $period)
                    ->where('status', 'predicted')
                    ->delete();

                $snapshot = DemandForecastSnapshot::create([
                    'product_id' => $productId,
                    'period_date' => $prediction['ds'],
                    'period_type' => $period,
                    'actual_demand' => 0, // Placeholder
                    'forecasted_demand' => $prediction['yhat'],
                    'confidence_lower' => $prediction['yhat_lower'],
                    'confidence_upper' => $prediction['yhat_upper'],
                    'mape' => $prediction['mape'] ?? $data['mape'] ?? null,
                    'status' => 'predicted',
                    'model_metadata' => [
                        'method' => $data['method'] ?? 'prophet',
                        'error_log' => $data['error_log'] ?? null,
                    ],
                ]);

                $savedSnapshots[] = $snapshot;
            }

            // Generate AI interpretation/summary for the latest forecast
            if (!empty($savedSnapshots)) {
                $latest = end($savedSnapshots);
                $aiSummary = $this->generateSummary($latest, $history);
                if ($aiSummary) {
                    foreach ($savedSnapshots as $snap) {
                        $snap->update(['ai_summary' => $aiSummary]);
                    }
                }
            }

            // Broadcast forecast completed event via WebSocket
            try {
                broadcast(new ForecastCompleted($productId, 'urun', $period));
            } catch (\Throwable $e) {
                Log::warning("ForecastCompleted broadcast failed: " . $e->getMessage());
            }

            return [
                'success' => true,
                'snapshots' => $savedSnapshots,
            ];

        } catch (\Throwable $e) {
            Log::error("Forecast failed for product #{$productId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Tahmin işlemi sırasında bir hata oluştu: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate an AI natural-language Turkish summary/explanation of the forecast.
     */
    public function generateSummary(DemandForecastSnapshot $snapshot, array $history): ?string
    {
        if (!$this->settings->bool('ai_brain_enabled', false)) {
            return null;
        }

        try {
            $productName = DB::table('tbUrunler')
                ->where('No', $snapshot->product_id)
                ->value('UrunID') ?? 'Bilinmeyen Ürün';

            $historyStr = collect($history)->take(-6)->map(fn($h) => "{$h['ds']}: {$h['y']} adet")->implode(', ');
            
            $prompt = implode("\n", [
                "Aşağıda bir ürünün geçmiş sipariş talepleri ve Prophet AI modeli tarafından üretilen gelecek talep tahmini bulunmaktadır.",
                "Ürün Adı: {$productName}",
                "Geçmiş Talep Örneği: {$historyStr}",
                "Tahmin Edilen Dönem Tarihi: " . $snapshot->period_date->format('Y-m-d'),
                "Tahmin Edilen Talep Miktarı: {$snapshot->forecasted_demand} adet",
                "Tahmin Güven Aralığı: {$snapshot->confidence_lower} ile {$snapshot->confidence_upper} adet arası",
                "Model Hata Oranı (MAPE): " . ($snapshot->mape ? ($snapshot->mape * 100) . "%" : "Belirsiz"),
                "",
                "Lütfen bu tahmin verilerini analiz et ve üretim planlamacısı için Türkçe 2-3 cümlelik çok kısa ve net bir öneri/açıklama metni yaz.",
                "Metinde doğrudan aksiyona odaklan (örneğin: talep artışı bekleniyor, stoklar artırılmalı veya talep stabil kalacak). Yanıtı doğrudan yaz, giriş veya açıklama ekleme."
            ]);

            return $this->aiBrain->soruCevapla($prompt, "forecast-summary-{$snapshot->product_id}");
        } catch (\Throwable $e) {
            Log::warning("Failed to generate AI forecast summary: " . $e->getMessage());
            return null;
        }
    }
}
