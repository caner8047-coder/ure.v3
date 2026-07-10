<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ForecastService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunDemandForecast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forecast:run {--weekly} {--product=} {--periods=6}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Talep tahmin modelini çalıştırır ve demand_forecast_snapshots tablosuna kaydeder';

    /**
     * Execute the console command.
     */
    public function handle(ForecastService $forecastService): int
    {
        $weekly = $this->option('weekly');
        $productId = $this->option('product');
        $periods = intval($this->option('periods') ?: 6);
        $periodType = $weekly ? 'weekly' : 'monthly';

        $this->info("Talep Tahmin Çalıştırılıyor...");
        $this->info("Dönem: " . strtoupper($periodType) . " | Gelecek Dönem Sayısı: " . $periods);

        if ($productId) {
            $productIds = [intval($productId)];
        } else {
            // Fetch all products that have matched order items in tbSiparisSatir to optimize forecasting runs
            if (!Schema::hasTable('tbSiparisSatir')) {
                $this->error("tbSiparisSatir tablosu bulunamadı.");
                return 1;
            }

            $productIds = DB::table('tbSiparisSatir')
                ->where('Aktif', 1)
                ->where('EslesenUrunTur', 'urun')
                ->whereNotNull('EslesenUrunNo')
                ->distinct()
                ->pluck('EslesenUrunNo')
                ->map(fn($id) => intval($id))
                ->toArray();
        }

        if (empty($productIds)) {
            $this->warn("Tahmin yapılacak sipariş geçmişine sahip ürün bulunamadı.");
            return 0;
        }

        $this->info(count($productIds) . " adet ürün tahmin işlemine alınıyor...");
        $bar = $this->output->createProgressBar(count($productIds));
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($productIds as $id) {
            $result = $forecastService->forecastProduct($id, $periodType, $periods);
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
                Log::warning("Forecast failed for product ID {$id}: " . ($result['message'] ?? 'Unknown error'));
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("İşlem Tamamlandı!");
        $this->table(
            ['Sonuç', 'Ürün Adedi'],
            [
                ['Başarılı', $successCount],
                ['Başarısız/Yetersiz Veri', $failCount]
            ]
        );

        return 0;
    }
}
