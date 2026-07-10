<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForecastTrainingDataBuilder
{
    public function __construct(
        protected ReportsService $reportsService
    ) {}

    /**
     * Get weekly historical demand data for a product.
     */
    public function getWeeklyProductHistory(int $productId, int $limitWeeks = 52): array
    {
        return $this->getHistory('urun', $productId, 'weekly', $limitWeeks);
    }

    /**
     * Get monthly historical demand data for a product.
     */
    public function getMonthlyProductHistory(int $productId, int $limitMonths = 24): array
    {
        return $this->getHistory('urun', $productId, 'monthly', $limitMonths);
    }

    /**
     * Helper to retrieve aggregated history data.
     */
    public function getHistory(string $type, int $id, string $period = 'monthly', int $limit = 24): array
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return [];
        }

        $dateSql = $this->reportsService->legacyDateSql('s.SiparisTarihi');

        // Choose date format depending on DB driver (SQLite vs MySQL)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        
        if ($period === 'weekly') {
            // Group by Year and Week
            $groupSql = $isSqlite 
                ? "strftime('%Y-%W', {$dateSql})"
                : "YEARWEEK({$dateSql})";
            
            $selectDateSql = $isSqlite
                ? "date({$dateSql}, 'weekday 0', '-6 days')" // Get Monday of the week in SQLite
                : "STR_TO_DATE(CONCAT(YEARWEEK({$dateSql}), ' Monday'), '%X%V %W')"; // Get Monday in MySQL
        } else {
            // Group by Year and Month
            $groupSql = $isSqlite
                ? "strftime('%Y-%m', {$dateSql})"
                : "DATE_FORMAT({$dateSql}, '%Y-%m')";
            
            $selectDateSql = $isSqlite
                ? "strftime('%Y-%m-01', {$dateSql})"
                : "DATE_FORMAT({$dateSql}, '%Y-%m-01')";
        }

        $query = DB::table('tbSiparisSatir as s')
            ->where('s.Aktif', 1)
            ->where('s.EslesenUrunNo', $id)
            ->where('s.EslesenUrunTur', $type === 'urun' ? 'urun' : 'ara')
            ->whereNotNull('s.SiparisTarihi')
            ->whereRaw("{$dateSql} IS NOT NULL")
            ->selectRaw("{$selectDateSql} as ds, SUM(s.Adet) as y")
            ->groupByRaw("{$groupSql}")
            ->orderByRaw("{$groupSql} ASC");

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row) {
            return [
                'ds' => $row->ds,
                'y' => (int) $row->y,
            ];
        })->toArray();
    }
}
