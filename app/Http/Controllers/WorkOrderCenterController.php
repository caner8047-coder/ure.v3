<?php

namespace App\Http\Controllers;

use App\Services\WorkOrderCenterQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class WorkOrderCenterController extends Controller
{
    public function __construct(
        protected WorkOrderCenterQueryService $queryService
    ) {}

    public function index()
    {
        return view('workorders.center');
    }

    public function feed(Request $request)
    {
        if (!$this->isCenterReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Is Emri Merkezi tabloları henüz olusturulmamis.',
            ], 503);
        }

        $feed = $this->queryService->feed($request->all());

        return response()->json([
            'success' => true,
            'data' => $feed->items(),
            'meta' => [
                'current_page' => $feed->currentPage(),
                'last_page' => $feed->lastPage(),
                'per_page' => $feed->perPage(),
                'total' => $feed->total(),
            ],
        ]);
    }

    public function entity(Request $request, string $type, int $id)
    {
        if (!$this->isCenterReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Is Emri Merkezi tabloları henüz olusturulmamis.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $this->queryService->entity($type, $id),
        ]);
    }

    public function timeline(Request $request)
    {
        if (!$this->isCenterReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Is Emri Merkezi tabloları henüz olusturulmamis.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $this->queryService->timelinePayload($request->all()),
        ]);
    }

    public function lookups()
    {
        return response()->json([
            'success' => true,
            'data' => $this->queryService->lookups(),
        ]);
    }

    public function export(Request $request)
    {
        if (!$this->isCenterReady()) {
            abort(503, 'Is Emri Merkezi tabloları henüz olusturulmamis.');
        }

        $rows = $this->queryService->exportRows($request->all());
        $fileName = 'is-emri-merkezi-' . Carbon::now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Olay Tarihi',
                'Baslik',
                'Ozet',
                'Yapan Kisi',
                'Yapan Tip',
                'Kaynak Ekran',
                'Kaynak Aksiyon',
                'Onceki Durum',
                'Yeni Durum',
                'Guncel Durum',
                'Asama',
                'Sonraki Adim',
                'Siparis No',
                'Siparis Satiri',
                'Gorev No',
                'Aggregate Tipi',
                'Aggregate Id',
                'Uyari Sayisi',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['happened_at'] ?? '',
                    $row['title_human'] ?? '',
                    $row['summary_human'] ?? '',
                    $row['actor_name'] ?? '',
                    $row['actor_type'] ?? '',
                    $row['source_screen'] ?? '',
                    $row['source_action'] ?? '',
                    $row['status_before'] ?? '',
                    $row['status_after'] ?? '',
                    data_get($row, 'snapshot.current_status', ''),
                    data_get($row, 'snapshot.current_stage', ''),
                    data_get($row, 'snapshot.next_expected_action', $row['next_step_human'] ?? ''),
                    $row['order_no'] ?? '',
                    $row['order_item_no'] ?? '',
                    $row['work_order_no'] ?? '',
                    $row['aggregate_type'] ?? '',
                    $row['aggregate_id'] ?? '',
                    data_get($row, 'snapshot.alert_count', 0),
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }

    private function isCenterReady(): bool
    {
        return Schema::hasTable('work_order_events') && Schema::hasTable('work_order_snapshots');
    }
}
