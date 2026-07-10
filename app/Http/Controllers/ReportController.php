<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ReportService;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Export Stock Status Report.
     */
    public function exportStocks(Request $request)
    {
        $format = $request->query('format', 'html');

        if ($format === 'csv') {
            $csv = $this->reportService->generateStockCsv();
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="stok-durum-raporu-' . date('Ymd-His') . '.csv"',
            ]);
        }

        $html = $this->reportService->generateStockHtml();
        return response($html);
    }

    /**
     * Export Production planning & task report.
     */
    public function exportProduction(Request $request)
    {
        $format = $request->query('format', 'html');

        if ($format === 'csv') {
            $csv = $this->reportService->generateProductionCsv();

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="uretim-plan-raporu-' . date('Ymd-His') . '.csv"',
            ]);
        }

        $html = $this->reportService->generateProductionHtml();
        return response($html);
    }
}
