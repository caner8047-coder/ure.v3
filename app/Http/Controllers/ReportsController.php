<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReportsService;

class ReportsController extends Controller
{
    protected ReportsService $reportsService;

    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
    }

    /**
     * Get dashboard stats summary.
     */
    public function dashboardStats()
    {
        $stats = $this->reportsService->getDashboardStats();
        return response()->json($stats);
    }

    /**
     * Get lookup values for reports.
     */
    public function lookups()
    {
        $lookups = $this->reportsService->getLookups();
        return response()->json($lookups);
    }

    /**
     * Get chart statistics.
     */
    public function chartData(Request $request)
    {
        $primary = $request->input('primary', 'departments');
        $secondary = $request->input('secondary', '0');
        $data = $this->reportsService->getChartData($primary, $secondary);

        return response()->json($data);
    }

    /**
     * Get list of personnel tasks.
     */
    public function getPersonnelTaskReport(Request $request)
    {
        $filters = $request->only(['personnel_id', 'search', 'date_filter', 'start_date', 'end_date', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 20);
        $payload = $this->reportsService->getPersonnelTaskReport($filters, $perPage, $request->url());

        return response()->json($payload);
    }

    /**
     * Export personnel tasks to Excel/XLSX.
     */
    public function exportPersonnelTasks(Request $request)
    {
        $filters = $request->only(['personnel_id', 'search', 'date_filter', 'start_date', 'end_date', 'sort_by', 'sort_dir']);
        $tasks = $this->reportsService->getRawPersonnelTasks($filters);
        
        $xlsx = $this->reportsService->buildPersonnelTasksWorkbook($tasks);
        $fileName = 'Gorevler_' . date('Ymd_His') . '.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Get task reports list.
     */
    public function getTaskReport(Request $request)
    {
        if ($request->input('report') === 'personnel-tasks') {
            return $this->getPersonnelTaskReport($request);
        }

        $filters = $request->only(['product_id', 'personnel_id', 'search', 'date_filter', 'start_date', 'end_date', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 20);
        $data = $this->reportsService->getTaskReport($filters, $perPage);

        return response()->json($data);
    }

    /**
     * Export tasks to CSV.
     */
    public function exportExcelTasks(Request $request)
    {
        if ($request->input('report') === 'personnel-tasks') {
            return $this->exportPersonnelTasks($request);
        }

        $filters = $request->only(['product_id', 'personnel_id', 'search', 'date_filter', 'start_date', 'end_date', 'sort_by', 'sort_dir']);
        $tasks = $this->reportsService->getRawTasksForExport($filters);

        $csvFileName = 'PersonelRapor_' . date('Ymd_His') . '.csv';
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($tasks) {
            $file = fopen('php://output', 'w');
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

    /**
     * Get personnel performance statistics.
     */
    public function getPerformanceReport(Request $request)
    {
        $data = $this->reportsService->getPerformanceReport();
        return response()->json(['success' => true, 'data' => $data]);
    }
}
