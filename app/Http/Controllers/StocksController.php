<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StockService;
use App\Services\StockExcelService;
use Illuminate\Support\Facades\Schema;

class StocksController extends Controller
{
    protected StockService $stockService;
    protected StockExcelService $excelService;

    public function __construct(StockService $stockService, StockExcelService $excelService)
    {
        $this->stockService = $stockService;
        $this->excelService = $excelService;
    }

    public function getStocks(Request $request)
    {
        $filters = $request->only(['department_id', 'component_type', 'search', 'stock_status', 'status', 'include_merged']);
        $sortBy = $request->input('sort_by', 'No');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = intval($request->input('per_page', 20));
        $page = intval($request->input('page', 1));

        $result = $this->stockService->getStocks($filters, $perPage, $page, $sortBy, $sortDir);

        return response()->json($result);
    }

    public function getLookups()
    {
        $lookups = $this->stockService->getLookups();
        return response()->json($lookups);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $this->stockService->storeStock($data);
        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $this->stockService->updateStock(intval($id), $data);
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $this->stockService->deleteStock(intval($id));
        return response()->json(['success' => true]);
    }

    public function resetBuffer()
    {
        $result = $this->stockService->resetBuffer();
        return response()->json($result);
    }

    public function exportCsv(Request $request)
    {
        return $this->exportWorkbook($request);
    }

    public function exportWorkbook(Request $request)
    {
        $filters = $request->only(['department_id', 'component_type', 'search', 'stock_status', 'status', 'include_merged']);
        $sortBy = $request->input('sort_by', 'No');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Export workbook logic (without pagination)
        $stocksData = $this->stockService->getStocks($filters, 1000000, 1, $sortBy, $sortDir);
        $content = $this->excelService->buildStockWorkbook($stocksData['data']);
        $fileName = 'Stoklar_' . date('Ymd_His') . '.xlsx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function importCsv(Request $request)
    {
        return $this->importWorkbook($request);
    }

    public function importWorkbook(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');
        $result = $this->stockService->importWorkbook($file);

        if (isset($result['success']) && !$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json($result);
    }

    public function getMovements(Request $request, int $id)
    {
        $filters = $request->only(['search']);
        $limit = intval($request->input('limit', 100));

        $result = $this->stockService->getMovements($id, $filters, $limit);
        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Stok kaydı bulunamadı.'], 404);
        }

        return response()->json(array_merge(['success' => true], $result));
    }

    public function getMovementFeed(Request $request)
    {
        $filters = $request->only(['search', 'component_no', 'department_no']);
        $perPage = intval($request->input('per_page', 25));

        $result = $this->stockService->getMovementFeed($filters, $perPage);

        return response()->json(array_merge(['success' => true], $result));
    }

    public function exportMovements(Request $request, int $id)
    {
        if (!Schema::hasTable('stock_movements')) {
            abort(404, 'Stok hareket tablosu bulunamadı.');
        }

        $filters = $request->only(['search']);
        $result = $this->stockService->getMovementsForExport($id, $filters);
        if (!$result) {
            abort(404, 'Stok kaydı bulunamadı.');
        }

        $stock = $result['stock'];
        $rows = $result['rows'];

        $fileName = 'Stok_Ekstresi_' . intval($stock->No) . '_' . date('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Tarih',
                'Başlık',
                'Yön',
                'Miktar Değişimi',
                'Önceki Adet',
                'Sonraki Adet',
                'Tampon Değişimi',
                'Önceki Tampon',
                'Sonraki Tampon',
                'Görevdeki Değişimi',
                'Önceki Görevdeki',
                'Sonraki Görevdeki',
                'Ürün',
                'Bölüm',
                'Kaynak',
                'Sipariş No',
                'Sipariş Satır No',
                'İş Emri No',
                'Personel Görev No',
                'Kullanıcı',
                'Neden',
                'Açıklama',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->happened_at,
                    $row->title_human,
                    $row->direction,
                    $row->quantity_delta,
                    $row->quantity_before,
                    $row->quantity_after,
                    $row->buffer_delta,
                    $row->free_before ?? $row->buffer_before,
                    $row->free_after ?? $row->buffer_after,
                    $row->reserved_delta,
                    $row->reserved_before,
                    $row->reserved_after,
                    $row->component_name,
                    $row->department_name,
                    $row->source_label ?? trim(($row->source_type ?? '') . ' ' . ($row->source_id ?? '')),
                    $row->order_no,
                    $row->order_item_no,
                    $row->work_order_no,
                    $row->personnel_task_no,
                    $row->actor_name,
                    $row->reason_human ?? '',
                    $row->description,
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }
}
