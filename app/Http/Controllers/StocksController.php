<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;
use App\Services\StockMovementLogger;

class StocksController extends Controller
{
    private array $movementOrderContextCache = [];

    public function getStocks(Request $request)
    {
        $query = $this->stockInventoryQuery();
        $this->applyStockFilters($query, $request);

        $sortField = $request->input('sort_by', 'No');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $this->applyStockSort($query, $sortField, $sortDir);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $total = $query->count();
        $data = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $this->attachSpecialProductionCapacityToStocks($data);

        return response()->json(['data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function getLookups()
    {
        $departments = DB::table('tbBolum')->select('No as id', 'BolumAdi as name')->orderBy('BolumAdi')->get();
        $componentTypesQuery = DB::table('tbAraUrun')
            ->select('UrunCesidi')
            ->whereNotNull('UrunCesidi')->where('UrunCesidi', '!=', '');
        $componentsQuery = DB::table('tbAraUrun')->select('No as id', 'AraUrunAdi as name');

        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo')) {
            $componentTypesQuery->where(function ($q) {
                $q->whereNull('MergedIntoNo')->orWhere('MergedIntoNo', 0);
            });
            $componentsQuery->where(function ($q) {
                $q->whereNull('MergedIntoNo')->orWhere('MergedIntoNo', 0);
            });
        }

        $componentTypes = $componentTypesQuery->distinct()->orderBy('UrunCesidi')->pluck('UrunCesidi');
        $components = $componentsQuery->orderBy('AraUrunAdi')->get();

        return response()->json(['departments' => $departments, 'componentTypes' => $componentTypes, 'components' => $components]);
    }

    public function store(Request $request)
    {
        $araUrunNo = intval($request->input('component_id', $request->input('AraUrunAdiNo', 0)));
        $adet = max(0, intval($request->input('quantity', $request->input('Adet', 0))));
        $tampon = $this->requestHasAny($request, ['buffer_quantity', 'TamponMiktar'])
            ? max(0, intval($request->input('buffer_quantity', $request->input('TamponMiktar', 0))))
            : $adet;
        $bolumAdiNo = intval($request->input('department_id', $request->input('BolumAdiNo', 0)));
        if ($bolumAdiNo <= 0) {
            $bolumAdiNo = intval(DB::table('tbAraUrun')->where('No', $araUrunNo)->value('BolumAdiNo') ?? 0);
        }

        $existingQuery = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo);
        if ($bolumAdiNo > 0) {
            $existingQuery->where('BolumAdiNo', $bolumAdiNo);
        }

        $existing = $existingQuery->first();
        $eskiAdet = $existing ? intval($existing->Adet) : 0;
        $stockRowNo = $existing ? intval($existing->No) : null;

        if ($existing) {
            $yeniAdetForRow = max(0, $eskiAdet + $adet);
            $yeniTamponForRow = $this->clampStockBuffer(
                $yeniAdetForRow,
                intval($existing->TamponMiktar ?? 0) + $tampon
            );
            DB::table('tbBolumAraStok')->where('No', $existing->No)->update([
                'Adet' => $yeniAdetForRow,
                'TamponMiktar' => $yeniTamponForRow,
            ]);
        } else {
            $tampon = $this->clampStockBuffer($adet, $tampon);
            $stockRowNo = DB::table('tbBolumAraStok')->insertGetId([
                'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                'AraUrunAdiNo' => $araUrunNo, 'Adet' => $adet, 'TamponMiktar' => $tampon
            ], 'No');
        }

        $updated = $stockRowNo
            ? DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first()
            : DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->first();
        $yeniAdet = intval($updated->Adet ?? 0);

        // ASP.NET SonrakiUrunAdetleriniGuncelle2 ve personelGorevTabloGuncelle
        $bomService = app(BomService::class);
        $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $yeniAdet);
        $bomService->personelGorevTabloGuncelle(strval($araUrunNo));

        $this->logStockMovement($existing, $updated, [
            'movement_type' => $existing ? 'manual_stock_added' : 'manual_stock_created',
            'source_type' => 'stock_screen',
            'source_id' => $updated->No ?? $stockRowNo,
            'description' => $existing ? 'Stok ekranından manuel miktar eklendi.' : 'Stok ekranından yeni stok kaydı oluşturuldu.',
            'metadata' => [
                'requested_quantity' => $adet,
                'requested_buffer_quantity' => $tampon,
            ],
        ]);

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        $eskiAdet = $record ? intval($record->Adet) : 0;
        $araUrunNo = $record ? intval($record->AraUrunAdiNo) : 0;

        $adet = max(0, intval($request->input('quantity', $request->input('Adet', 0))));
        $preserveReserved = filter_var($request->input('preserve_reserved', false), FILTER_VALIDATE_BOOL);
        $tampon = $preserveReserved || !$this->requestHasAny($request, ['buffer_quantity', 'TamponMiktar'])
            ? $this->bufferPreservingReservedQuantity($record, $adet)
            : intval($request->input('buffer_quantity', $request->input('TamponMiktar', 0)));
        $tampon = $this->clampStockBuffer($adet, $tampon);
        DB::table('tbBolumAraStok')->where('No', $id)->update(['Adet' => $adet, 'TamponMiktar' => $tampon]);

        // Zincirleme havuz ve görev güncelleme
        if ($araUrunNo > 0) {
            $bomService = app(BomService::class);
            $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $adet);
            $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
        }

        $updated = DB::table('tbBolumAraStok')->where('No', $id)->first();
        $this->logStockMovement($record, $updated, [
            'movement_type' => 'manual_stock_updated',
            'source_type' => 'stock_screen',
            'source_id' => $id,
            'description' => 'Stok ekranından manuel düzeltme yapıldı.',
            'metadata' => [
                'requested_quantity' => $adet,
                'requested_buffer_quantity' => $tampon,
            ],
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        if ($record) {
            $araUrunNo = intval($record->AraUrunAdiNo);
            $eskiAdet = intval($record->Adet);

            DB::table('tbBolumAraStok')->where('No', $id)->delete();

            $this->logStockMovement($record, null, [
                'movement_type' => 'manual_stock_deleted',
                'source_type' => 'stock_screen',
                'source_id' => $id,
                'description' => 'Stok ekranından stok kaydı silindi.',
            ]);

            // Zincirleme güncelleme (silindiğinde 0'a düşer)
            if ($araUrunNo > 0) {
                $bomService = app(BomService::class);
                $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, 0);
                $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
            }
        } else {
            DB::table('tbBolumAraStok')->where('No', $id)->delete();
        }
        return response()->json(['success' => true]);
    }

    public function resetBuffer()
    {
        $bomService = app(BomService::class);
        $beforeRows = DB::table('tbBolumAraStok')->get()->keyBy('No');
        foreach ($beforeRows as $row) {
            $componentNo = intval($row->AraUrunAdiNo ?? 0);
            if ($componentNo > 0 && $bomService->hasActiveBufferReservation($componentNo)) {
                continue;
            }

            DB::table('tbBolumAraStok')->where('No', $row->No)->update(['TamponMiktar' => $row->Adet]);
        }

        $afterRows = DB::table('tbBolumAraStok')->get();

        foreach ($afterRows as $after) {
            $before = $beforeRows->get($after->No);
            if (!$before || intval($before->TamponMiktar ?? 0) === intval($after->TamponMiktar ?? 0)) {
                continue;
            }

            $this->logStockMovement($before, $after, [
                'movement_type' => 'buffer_reset',
                'source_type' => 'stock_screen',
                'source_id' => $after->No,
                'description' => 'Tampon miktarı depodaki adet ile eşitlendi.',
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function exportCsv(Request $request)
    {
        return $this->exportWorkbook($request);
    }

    public function exportWorkbook(Request $request)
    {
        $query = $this->stockInventoryQuery();
        $this->applyStockFilters($query, $request);

        $sortField = $request->input('sort_by', 'No');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $this->applyStockSort($query, $sortField, $sortDir);

        $stocks = $query->get();
        $fileName = 'Stoklar_' . date('Ymd_His') . '.xlsx';
        $content = $this->buildStockWorkbook($stocks);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Import stock data from a spreadsheet file.
     * Expected columns: No, Bölüm, Ara Ürün, Ürün Çeşidi, Adet, Görevdeki, Boşta/Tampon
     * Only "No", "Adet" and "Boşta/Tampon" are used for updating.
     */
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

        try {
            $rows = $this->readStockImportRows($file);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Dosya okunamadı.',
            ], 422);
        }

        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && $this->spreadsheetRowHasContent($row)));

        if (count($rows) < 2) {
            return response()->json(['success' => false, 'message' => 'İçeri aktarılacak satır bulunamadı.'], 422);
        }

        $header = array_shift($rows);
        $noIdx = $this->findCsvHeaderIndex($header, ['No', 'Stok No', 'stock_row_no', 'id']);
        $adetIdx = $this->findCsvHeaderIndex($header, ['Adet', 'Miktar', 'quantity', 'quantity_after']);
        $tamponIdx = $this->findCsvHeaderIndex($header, ['Boşta/Tampon', 'Boşta', 'Bosta', 'Tampon', 'Tampon Miktar', 'TamponMiktar', 'buffer_quantity', 'buffer_after']);

        if ($noIdx === false || $adetIdx === false || $tamponIdx === false) {
            return response()->json([
                'success' => false,
                'message' => 'Dosya başlıkları uygun değil. "No", "Adet" ve "Boşta/Tampon" sütunları gerekli.'
            ], 422);
        }

        $updated = 0;
        $errors = 0;
        $bomService = app(BomService::class);

        $lineNumber = 1;
        foreach ($rows as $row) {
            $lineNumber++;
            if (count($row) <= max($noIdx, $adetIdx, $tamponIdx)) {
                $errors++;
                continue;
            }

            $noRaw = $this->normalizeSpreadsheetText($row[$noIdx] ?? '');
            if ($noRaw === '') {
                continue;
            }

            $no = $this->parseCsvInteger($noRaw);
            $adet = $this->parseCsvInteger($row[$adetIdx]);
            $tampon = $this->parseCsvInteger($row[$tamponIdx]);

            if ($no <= 0) {
                $errors++;
                continue;
            }

            $record = DB::table('tbBolumAraStok')->where('No', $no)->first();
            if (!$record) {
                $errors++;
                continue;
            }

            $eskiAdet = intval($record->Adet);
            $araUrunNo = intval($record->AraUrunAdiNo);

            DB::table('tbBolumAraStok')->where('No', $no)->update([
                'Adet' => $adet,
                'TamponMiktar' => $tampon,
            ]);

            // Cascade BOM updates
            if ($araUrunNo > 0) {
                $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $adet);
                $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
            }

            $updatedRecord = DB::table('tbBolumAraStok')->where('No', $no)->first();
            $this->logStockMovement($record, $updatedRecord, [
                'movement_type' => 'stock_spreadsheet_imported',
                'source_type' => 'stock_import',
                'source_id' => $file->getClientOriginalName(),
                'description' => 'Stok çalışma kitabı içe aktarımı ile stok güncellendi.',
                'metadata' => [
                    'file_name' => $file->getClientOriginalName(),
                    'line_number' => $lineNumber,
                ],
            ]);

            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$updated} kayıt güncellendi"
                . ($errors > 0 ? ", {$errors} satır hatalı olduğu için atlandı" : '')
                . '.',
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    public function getMovements(Request $request, int $id)
    {
        $stock = $this->stockDetailQuery()
            ->where('s.No', $id)
            ->first();

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stok kaydı bulunamadı.'], 404);
        }

        if (!Schema::hasTable('stock_movements')) {
            return response()->json([
                'success' => true,
                'stock' => $stock,
                'movements' => [],
                'analysis' => [
                    'current' => $this->stockBalanceSnapshot($stock),
                    'formula' => [
                        'quantity_label' => 'Depodaki',
                        'free_label' => 'Boşta',
                        'reserved_label' => 'Görevdeki',
                        'text' => 'Görevdeki = Depodaki - Boşta',
                    ],
                    'opening' => $this->stockBalanceSnapshot($stock),
                    'net' => ['quantity_delta' => 0, 'free_delta' => 0, 'reserved_delta' => 0],
                    'expected' => $this->stockBalanceSnapshot($stock),
                    'reconciled' => ['quantity' => true, 'free' => true, 'reserved' => true],
                    'movement_count_total' => 0,
                    'source_totals' => [],
                    'reserved_sources' => [],
                    'movement_type_totals' => [],
                    'live_context' => ['pool' => ['count' => 0], 'personnel_tasks' => ['count' => 0], 'orders' => ['count' => 0]],
                ],
                'summary' => ['movement_count' => 0, 'total_in' => 0, 'total_out' => 0, 'last_movement_at' => null],
            ]);
        }

        $baseQuery = $this->stockMovementQueryForStock($stock, $id);
        $query = clone $baseQuery;

        $this->applyMovementFilters($query, $request);

        $limit = max(1, min(200, intval($request->input('limit', 100))));
        $movements = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->limit($limit)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();
        $movements = $this->enrichMovementRows($movements);
        $analysis = $this->buildStockMovementAnalysis($stock, $baseQuery);

        return response()->json([
            'success' => true,
            'stock' => $stock,
            'movements' => $movements,
            'analysis' => $analysis,
            'summary' => [
                'movement_count' => $movements->count(),
                'total_in' => intval($movements->where('quantity_delta', '>', 0)->sum('quantity_delta')),
                'total_out' => abs(intval($movements->where('quantity_delta', '<', 0)->sum('quantity_delta'))),
                'last_movement_at' => optional($movements->first())->happened_at,
            ],
        ]);
    }

    public function getMovementFeed(Request $request)
    {
        if (!Schema::hasTable('stock_movements')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('stock_movements as m')
            ->leftJoin('tbAraUrun as a', 'm.component_no', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'm.department_no', '=', 'b.No');

        $this->applyMovementFilters($query, $request);

        if ($request->filled('component_no')) {
            $query->where('m.component_no', intval($request->input('component_no')));
        }

        if ($request->filled('department_no')) {
            $query->where('m.department_no', intval($request->input('department_no')));
        }

        $perPage = max(1, min(100, intval($request->input('per_page', 25))));
        $rows = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->paginate($perPage);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function exportMovements(Request $request, int $id)
    {
        if (!Schema::hasTable('stock_movements')) {
            abort(404, 'Stok hareket tablosu bulunamadı.');
        }

        $stock = $this->stockDetailQuery()
            ->where('s.No', $id)
            ->first();

        if (!$stock) {
            abort(404, 'Stok kaydı bulunamadı.');
        }

        $query = $this->stockMovementQueryForStock($stock, $id);

        $this->applyMovementFilters($query, $request);

        $rows = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->limit(5000)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();
        $rows = $this->enrichMovementRows($rows);

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

    private function stockMovementQueryForStock(object $stock, int $id)
    {
        return DB::table('stock_movements as m')
            ->leftJoin('tbAraUrun as a', 'm.component_no', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'm.department_no', '=', 'b.No')
            ->where(function ($q) use ($stock, $id) {
                $q->where('m.stock_row_no', $id)
                    ->orWhere(function ($componentQuery) use ($stock) {
                        $componentQuery
                            ->where('m.component_no', intval($stock->AraUrunAdiNo ?? 0))
                            ->where(function ($departmentQuery) use ($stock) {
                                $departmentNo = intval($stock->BolumAdiNo ?? 0);
                                if ($departmentNo > 0) {
                                    $departmentQuery->where('m.department_no', $departmentNo);
                                } else {
                                    $departmentQuery->whereNull('m.department_no');
                                }
                            });
                    });
            });
    }

    private function enrichMovementRows($rows)
    {
        return collect($rows)->map(fn ($row) => $this->enrichMovementRow($row));
    }

    private function enrichMovementRow(object $row): object
    {
        $metadata = $this->decodeJsonField($row->metadata ?? null);
        $row->metadata = $metadata;

        $this->attachDerivedOrderContext($row, $metadata);

        $quantityBefore = $this->nullableMovementInt($row->quantity_before ?? null);
        $quantityAfter = $this->nullableMovementInt($row->quantity_after ?? null);
        $bufferBefore = $this->nullableMovementInt($row->buffer_before ?? null);
        $bufferAfter = $this->nullableMovementInt($row->buffer_after ?? null);

        $freeBefore = $this->freeBalance($quantityBefore, $bufferBefore);
        $freeAfter = $this->freeBalance($quantityAfter, $bufferAfter);
        $reservedBefore = $this->reservedBalance($quantityBefore, $bufferBefore);
        $reservedAfter = $this->reservedBalance($quantityAfter, $bufferAfter);

        $freeDelta = ($freeBefore !== null && $freeAfter !== null)
            ? $freeAfter - $freeBefore
            : intval($row->buffer_delta ?? 0);
        $reservedDelta = ($reservedBefore !== null && $reservedAfter !== null)
            ? $reservedAfter - $reservedBefore
            : intval($row->quantity_delta ?? 0) - $freeDelta;

        $row->free_before = $freeBefore;
        $row->free_delta = $freeDelta;
        $row->free_after = $freeAfter;
        $row->reserved_before = $reservedBefore;
        $row->reserved_delta = $reservedDelta;
        $row->reserved_after = $reservedAfter;
        $row->source_label = $this->movementSourceLabel($row);
        $row->reason_human = $this->movementReasonHuman($row, $metadata);
        $row->direction_label = $this->movementDirectionLabel((string) ($row->direction ?? 'neutral'));

        return $row;
    }

    private function attachDerivedOrderContext(object $row, array &$metadata): void
    {
        if (
            intval($row->order_item_no ?? 0) > 0
            || !Schema::hasTable('tbSiparisSatir')
            || !Schema::hasColumn('tbSiparisSatir', 'TamponDusumleri')
        ) {
            return;
        }

        $movementType = (string) ($row->movement_type ?? '');
        if (!in_array($movementType, ['work_order_buffer_reserved', 'work_order_buffer_released'], true)) {
            return;
        }

        $componentNo = intval($row->component_no ?? 0);
        $happenedAt = trim((string) ($row->happened_at ?? ''));
        if ($componentNo <= 0 || $happenedAt === '') {
            return;
        }

        $order = $this->findOrderForLegacyBufferMovement($componentNo, $happenedAt);
        if (!$order) {
            return;
        }

        $row->order_item_no = intval($order->No ?? 0) ?: null;
        $row->order_no = trim((string) ($order->SiparisNo ?? '')) ?: null;
        $row->work_order_no = intval($order->GorevNo ?? 0) ?: null;
        $row->derived_order_context = true;

        $metadata['derived_order_context'] = [
            'order_item_no' => $row->order_item_no,
            'order_no' => $row->order_no,
            'work_order_no' => $row->work_order_no,
            'matched_by' => 'movement_time_and_buffer_deductions',
        ];
        $row->metadata = $metadata;
    }

    private function findOrderForLegacyBufferMovement(int $componentNo, string $happenedAt): ?object
    {
        $cacheKey = $componentNo . '|' . substr($happenedAt, 0, 19);
        if (array_key_exists($cacheKey, $this->movementOrderContextCache)) {
            return $this->movementOrderContextCache[$cacheKey];
        }

        try {
            $moment = \Carbon\Carbon::parse($happenedAt);
        } catch (\Throwable) {
            return $this->movementOrderContextCache[$cacheKey] = null;
        }

        $likeNeedles = [
            '%"araNo":' . $componentNo . '%',
            '%"araNo": ' . $componentNo . '%',
        ];

        $query = DB::table('tbSiparisSatir')
            ->whereNotNull('TamponDusumleri')
            ->where(function ($q) use ($likeNeedles) {
                foreach ($likeNeedles as $needle) {
                    $q->orWhere('TamponDusumleri', 'like', $needle);
                }
            });

        if (Schema::hasColumn('tbSiparisSatir', 'IsEmriTarihi')) {
            $query->whereBetween('IsEmriTarihi', [
                $moment->copy()->subSeconds(3)->format('Y-m-d H:i:s'),
                $moment->copy()->addSeconds(3)->format('Y-m-d H:i:s'),
            ]);
        }

        return $this->movementOrderContextCache[$cacheKey] = $query
            ->orderByDesc('No')
            ->first($this->existingColumns('tbSiparisSatir', [
                'No',
                'SiparisNo',
                'Musteri',
                'UrunAdi',
                'Adet',
                'Durum',
                'GorevNo',
                'IsEmriTarihi',
            ]));
    }

    private function buildStockMovementAnalysis(object $stock, $baseQuery): array
    {
        $current = $this->stockBalanceSnapshot($stock);
        $allMovements = (clone $baseQuery)
            ->orderBy('m.happened_at')
            ->orderBy('m.id')
            ->limit(5000)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();

        $allMovements = $this->enrichMovementRows($allMovements);
        $netQuantity = intval($allMovements->sum(fn ($row) => intval($row->quantity_delta ?? 0)));
        $netFree = intval($allMovements->sum(fn ($row) => intval($row->free_delta ?? 0)));
        $netReserved = intval($allMovements->sum(fn ($row) => intval($row->reserved_delta ?? 0)));
        $firstMovement = $allMovements->first();

        $openingQuantity = $firstMovement && $firstMovement->quantity_before !== null
            ? intval($firstMovement->quantity_before)
            : $current['quantity'] - $netQuantity;
        $openingFree = $firstMovement && $firstMovement->free_before !== null
            ? intval($firstMovement->free_before)
            : $current['free'] - $netFree;
        $openingQuantity = max(0, $openingQuantity);
        $openingFree = max(0, min($openingQuantity, $openingFree));
        $openingReserved = max(0, $openingQuantity - $openingFree);

        $expectedQuantity = $openingQuantity + $netQuantity;
        $expectedFree = $openingFree + $netFree;
        $expectedFree = max(0, min(max(0, $expectedQuantity), $expectedFree));
        $expectedReserved = max(0, $expectedQuantity - $expectedFree);

        return [
            'current' => $current,
            'formula' => [
                'quantity_label' => 'Depodaki',
                'free_label' => 'Boşta',
                'reserved_label' => 'Görevdeki',
                'text' => 'Görevdeki = Depodaki - Boşta',
            ],
            'opening' => [
                'quantity' => $openingQuantity,
                'free' => $openingFree,
                'reserved' => $openingReserved,
            ],
            'net' => [
                'quantity_delta' => $netQuantity,
                'free_delta' => $netFree,
                'reserved_delta' => $netReserved,
            ],
            'expected' => [
                'quantity' => $expectedQuantity,
                'free' => $expectedFree,
                'reserved' => $expectedReserved,
            ],
            'reconciled' => [
                'quantity' => $expectedQuantity === $current['quantity'],
                'free' => $expectedFree === $current['free'],
                'reserved' => $expectedReserved === $current['reserved'],
            ],
            'movement_count_total' => $allMovements->count(),
            'source_totals' => $this->buildMovementSourceTotals($allMovements),
            'reserved_sources' => $this->buildReservedSourceBreakdown($allMovements),
            'movement_type_totals' => $this->buildMovementTypeTotals($allMovements),
            'live_context' => $this->buildLiveStockContext($stock, $allMovements),
        ];
    }

    private function stockBalanceSnapshot(object $stock): array
    {
        $quantity = max(0, intval($stock->Adet ?? 0));
        $free = max(0, min($quantity, intval($stock->TamponMiktar ?? 0)));

        return [
            'quantity' => $quantity,
            'free' => $free,
            'reserved' => max(0, $quantity - $free),
        ];
    }

    private function buildReservedSourceBreakdown($movements): array
    {
        return array_values(array_filter(
            $this->buildMovementSourceTotals($movements),
            fn ($group) => intval($group['reserved_delta'] ?? 0) !== 0
        ));
    }

    private function buildMovementSourceTotals($movements): array
    {
        $groups = [];

        foreach ($movements as $movement) {
            $reservedDelta = intval($movement->reserved_delta ?? 0);
            $quantityDelta = intval($movement->quantity_delta ?? 0);
            $freeDelta = intval($movement->free_delta ?? 0);
            if ($reservedDelta === 0 && $quantityDelta === 0 && $freeDelta === 0) {
                continue;
            }

            $key = $this->movementAllocationKey($movement);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $this->movementAllocationLabel($movement),
                    'movement_type' => (string) ($movement->movement_type ?? ''),
                    'movement_title' => (string) ($movement->title_human ?? ''),
                    'source_label' => (string) ($movement->source_label ?? ''),
                    'quantity_delta' => 0,
                    'free_delta' => 0,
                    'reserved_delta' => 0,
                    'movement_count' => 0,
                    'first_at' => null,
                    'last_at' => null,
                ];
            }

            $groups[$key]['quantity_delta'] += $quantityDelta;
            $groups[$key]['free_delta'] += $freeDelta;
            $groups[$key]['reserved_delta'] += $reservedDelta;
            $groups[$key]['movement_count']++;
            $date = (string) ($movement->happened_at ?? '');
            if ($date !== '') {
                $groups[$key]['first_at'] = $groups[$key]['first_at'] === null
                    ? $date
                    : min($groups[$key]['first_at'], $date);
                $groups[$key]['last_at'] = $groups[$key]['last_at'] === null
                    ? $date
                    : max($groups[$key]['last_at'], $date);
            }
        }

        $groups = array_values($groups);
        usort($groups, function ($a, $b) {
            $deltaCompare = max(
                abs(intval($b['quantity_delta'] ?? 0)),
                abs(intval($b['free_delta'] ?? 0)),
                abs(intval($b['reserved_delta'] ?? 0))
            ) <=> max(
                abs(intval($a['quantity_delta'] ?? 0)),
                abs(intval($a['free_delta'] ?? 0)),
                abs(intval($a['reserved_delta'] ?? 0))
            );
            if ($deltaCompare !== 0) {
                return $deltaCompare;
            }

            return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
        });

        return $groups;
    }

    private function buildMovementTypeTotals($movements): array
    {
        $totals = [];

        foreach ($movements as $movement) {
            $type = (string) ($movement->movement_type ?? 'stock_adjusted');
            if (!isset($totals[$type])) {
                $totals[$type] = [
                    'movement_type' => $type,
                    'title' => (string) ($movement->title_human ?? str_replace('_', ' ', $type)),
                    'quantity_delta' => 0,
                    'free_delta' => 0,
                    'reserved_delta' => 0,
                    'movement_count' => 0,
                ];
            }

            $totals[$type]['quantity_delta'] += intval($movement->quantity_delta ?? 0);
            $totals[$type]['free_delta'] += intval($movement->free_delta ?? 0);
            $totals[$type]['reserved_delta'] += intval($movement->reserved_delta ?? 0);
            $totals[$type]['movement_count']++;
        }

        return array_values($totals);
    }

    private function buildLiveStockContext(object $stock, $movements): array
    {
        $componentNo = intval($stock->AraUrunAdiNo ?? 0);
        $departmentNo = intval($stock->BolumAdiNo ?? 0);
        $context = [
            'pool' => ['count' => 0, 'total_quantity' => 0, 'ready_quantity' => 0, 'rows' => []],
            'personnel_tasks' => ['count' => 0, 'total_quantity' => 0, 'ready_quantity' => 0, 'waiting_quantity' => 0, 'rows' => []],
            'orders' => ['count' => 0, 'rows' => []],
        ];

        if ($componentNo > 0 && Schema::hasTable('tbBolumHavuz')) {
            $poolRows = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', $componentNo)
                ->when(
                    $departmentNo > 0 && Schema::hasColumn('tbBolumHavuz', 'BolumAdiNo'),
                    fn ($q) => $q->where('BolumAdiNo', $departmentNo)
                )
                ->orderBy('No')
                ->limit(20)
                ->get($this->existingColumns('tbBolumHavuz', ['No', 'SiparisSatirNo', 'SiparisNo', 'ToplamAdet', 'Adet', 'Aciklama']));

            $context['pool'] = [
                'count' => $poolRows->count(),
                'total_quantity' => intval($poolRows->sum(fn ($row) => intval($row->ToplamAdet ?? 0))),
                'ready_quantity' => intval($poolRows->sum(fn ($row) => intval($row->Adet ?? 0))),
                'rows' => $poolRows->values(),
            ];
        }

        if ($componentNo > 0 && Schema::hasTable('tbPersonelGorev')) {
            $taskRows = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $componentNo)
                ->when(
                    $departmentNo > 0 && Schema::hasColumn('tbPersonelGorev', 'BolumAdiNo'),
                    fn ($q) => $q->where('BolumAdiNo', $departmentNo)
                )
                ->orderBy('No')
                ->limit(20)
                ->get($this->existingColumns('tbPersonelGorev', ['No', 'SiparisSatirNo', 'SiparisNo', 'PersonelNo', 'Adet', 'BekleyenAdet', 'Onay']));

            $context['personnel_tasks'] = [
                'count' => $taskRows->count(),
                'total_quantity' => intval($taskRows->sum(fn ($row) => intval($row->Adet ?? 0) + intval($row->BekleyenAdet ?? 0))),
                'ready_quantity' => intval($taskRows->sum(fn ($row) => intval($row->Adet ?? 0))),
                'waiting_quantity' => intval($taskRows->sum(fn ($row) => intval($row->BekleyenAdet ?? 0))),
                'rows' => $taskRows->values(),
            ];
        }

        if (Schema::hasTable('tbSiparisSatir')) {
            $orderItemNos = $movements
                ->pluck('order_item_no')
                ->filter(fn ($value) => intval($value) > 0)
                ->map(fn ($value) => intval($value))
                ->unique()
                ->values();

            if ($orderItemNos->isNotEmpty()) {
                $orderRows = DB::table('tbSiparisSatir')
                    ->whereIn('No', $orderItemNos->all())
                    ->orderByDesc('No')
                    ->limit(20)
                    ->get($this->existingColumns('tbSiparisSatir', ['No', 'SiparisNo', 'Musteri', 'UrunAdi', 'Adet', 'Durum', 'GorevNo']));

                $context['orders'] = [
                    'count' => $orderRows->count(),
                    'rows' => $orderRows->values(),
                ];
            }
        }

        return $context;
    }

    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
    }

    private function movementAllocationKey(object $movement): string
    {
        if (intval($movement->order_item_no ?? 0) > 0) {
            return 'order_item:' . intval($movement->order_item_no);
        }
        if (trim((string) ($movement->order_no ?? '')) !== '') {
            return 'order_no:' . trim((string) $movement->order_no);
        }
        if (intval($movement->work_order_no ?? 0) > 0) {
            return 'work_order:' . intval($movement->work_order_no);
        }
        if (intval($movement->personnel_task_no ?? 0) > 0) {
            return 'personnel_task:' . intval($movement->personnel_task_no);
        }

        return implode(':', [
            'movement',
            (string) ($movement->movement_type ?? 'stock_adjusted'),
            (string) ($movement->source_type ?? ''),
            (string) ($movement->source_id ?? ''),
        ]);
    }

    private function movementAllocationLabel(object $movement): string
    {
        $parts = [];
        if (trim((string) ($movement->order_no ?? '')) !== '') {
            $parts[] = 'Sipariş ' . trim((string) $movement->order_no);
        }
        if (intval($movement->order_item_no ?? 0) > 0) {
            $parts[] = 'Satır #' . intval($movement->order_item_no);
        }
        if (intval($movement->work_order_no ?? 0) > 0) {
            $parts[] = 'İş emri #' . intval($movement->work_order_no);
        }
        if (intval($movement->personnel_task_no ?? 0) > 0) {
            $parts[] = 'Görev #' . intval($movement->personnel_task_no);
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : ($movement->source_label ?: ($movement->title_human ?: 'Stok hareketi'));
    }

    private function movementSourceLabel(object $movement): string
    {
        $parts = [];
        if (trim((string) ($movement->order_no ?? '')) !== '') {
            $parts[] = 'Sipariş ' . trim((string) $movement->order_no);
        }
        if (intval($movement->order_item_no ?? 0) > 0) {
            $parts[] = 'Satır #' . intval($movement->order_item_no);
        }
        if (intval($movement->work_order_no ?? 0) > 0) {
            $parts[] = 'İş emri #' . intval($movement->work_order_no);
        }
        if (intval($movement->personnel_task_no ?? 0) > 0) {
            $parts[] = 'Görev #' . intval($movement->personnel_task_no);
        }
        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        $sourceType = trim((string) ($movement->source_type ?? ''));
        $sourceId = trim((string) ($movement->source_id ?? ''));
        $componentNo = intval($movement->component_no ?? 0);
        $movementType = (string) ($movement->movement_type ?? '');

        if (
            in_array($movementType, ['work_order_buffer_reserved', 'work_order_buffer_released', 'pool_buffer_released'], true)
            && $sourceId !== ''
            && ctype_digit($sourceId)
            && intval($sourceId) === $componentNo
        ) {
            return 'Ara ürün #' . $sourceId;
        }

        $sourceLabel = match ($sourceType) {
            'stock_screen' => 'Stok ekranı',
            'stock_import' => 'Stok aktarımı',
            'personnel_task' => 'Personel görevi',
            'order_item' => 'Sipariş satırı',
            'order_item_work_order' => 'Sipariş iş emri',
            'work_order_reservation' => 'İş emri rezervi',
            'component_reservation' => 'Ara stok rezervi',
            'work_order_cancel' => 'İş emri iptali',
            'production_pool' => 'Üretim havuzu',
            'bom_rebalance' => 'BOM dengeleme',
            'pasif_devam_eden_completion' => 'Pasif devam eden kapanış',
            'personnel_task_start' => 'Görev üretime alma',
            'stock_reset' => 'Operasyonel sıfırlama',
            default => $sourceType !== '' ? str_replace('_', ' ', $sourceType) : '',
        };

        if ($sourceLabel !== '' && $sourceId !== '') {
            return $sourceLabel . ' #' . $sourceId;
        }

        return $sourceLabel;
    }

    private function movementReasonHuman(object $movement, array $metadata): string
    {
        $context = is_array($metadata['context'] ?? null) ? $metadata['context'] : $metadata;
        $type = (string) ($movement->movement_type ?? '');
        $quantityDelta = intval($movement->quantity_delta ?? 0);
        $freeDelta = intval($movement->free_delta ?? $movement->buffer_delta ?? 0);
        $reservedForOrder = filter_var($context['reserved_for_order'] ?? false, FILTER_VALIDATE_BOOL);

        return match ($type) {
            'work_order_buffer_reserved' => 'İş emri açılırken boşta stok bu işe ayrıldı; depodaki değişmez, boşta azalır, görevdeki artar.',
            'work_order_buffer_released', 'pool_buffer_released' => 'İptal veya havuz geri dönüşü nedeniyle ayrılmış boşta stok iade edildi; görevdeki azalır.',
            'production_stock_in' => ($reservedForOrder || ($quantityDelta > 0 && $freeDelta === 0))
                ? 'Üretim sipariş/iş emri için kayda alındı; depodaki arttı fakat boşta artmadığı için görevdeki yükseldi.'
                : 'Üretim boşta stoğa eklendi; depodaki ve boşta birlikte arttı.',
            'production_stock_in_reversed', 'cancelled_production_stock_in' => 'Üretim girişi geri alındı; önceki stok dengesi ters hareketle düzeltildi.',
            'production_component_consumed_by_parent' => 'Üst ürün üretime alındığı için bu alt parça stoktan tüketildi.',
            'order_stock_out', 'order_auto_stock_out' => 'Sipariş stoktan karşılandı; depodaki stok ve varsa boşta stok düşürüldü.',
            'order_stock_out_reversed', 'order_auto_stock_out_reversed' => 'Stoktan karşılama geri alındı; stok ve boşta miktar iade edildi.',
            'manual_stock_created', 'manual_stock_added', 'manual_stock_updated' => 'Stok ekranından manuel düzeltme yapıldı; bu satır mevcut dengeyi doğrudan değiştirir.',
            'stock_spreadsheet_imported', 'csv_stock_imported' => 'Excel/CSV aktarımı mevcut stok değerlerini güncelledi.',
            'buffer_reset' => 'Boşta miktar depodaki adetle eşitlendi.',
            default => trim((string) ($movement->description ?? '')) !== ''
                ? (string) $movement->description
                : 'Stok hareketi mevcut dengeye uygulandı.',
        };
    }

    private function movementDirectionLabel(string $direction): string
    {
        return match ($direction) {
            'in' => 'Giriş',
            'out' => 'Çıkış',
            'reserve' => 'Ayırma',
            'release' => 'İade',
            default => 'Nötr',
        };
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true) ?: [];
        }
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableMovementInt(mixed $value): ?int
    {
        return $value === null ? null : intval($value);
    }

    private function freeBalance(?int $quantity, ?int $buffer): ?int
    {
        if ($quantity === null && $buffer === null) {
            return null;
        }

        $quantity = max(0, intval($quantity ?? 0));
        $buffer = max(0, intval($buffer ?? 0));

        return min($quantity, $buffer);
    }

    private function reservedBalance(?int $quantity, ?int $buffer): ?int
    {
        if ($quantity === null && $buffer === null) {
            return null;
        }

        $quantity = max(0, intval($quantity ?? 0));
        $free = $this->freeBalance($quantity, $buffer) ?? 0;

        return max(0, $quantity - $free);
    }

    private function stockDetailQuery()
    {
        return DB::table('tbBolumAraStok as s')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->select(
                's.No',
                's.BolumAdiNo',
                's.AraUrunAdiNo',
                's.UrunIDNo',
                's.Adet',
                's.TamponMiktar',
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi")
            );
    }

    private function stockInventoryQuery()
    {
        return DB::table('tbAraUrun as a')
            ->leftJoin('tbBolumAraStok as s', 's.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', function ($join) {
                $join->on('b.No', '=', DB::raw('COALESCE(s.BolumAdiNo, a.BolumAdiNo)'));
            })
            ->select(
                's.No',
                DB::raw('s.No as StockNo'),
                DB::raw('COALESCE(s.BolumAdiNo, a.BolumAdiNo) as BolumAdiNo'),
                DB::raw('a.No as AraUrunAdiNo'),
                DB::raw('COALESCE(s.Adet, 0) as Adet'),
                DB::raw('COALESCE(s.TamponMiktar, 0) as TamponMiktar'),
                DB::raw('COALESCE(s.UrunIDNo, 0) as UrunIDNo'),
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi"),
                DB::raw('CASE WHEN s.No IS NULL THEN 1 ELSE 0 END as IsVirtualStock')
            );
    }

    private function applyStockFilters($query, Request $request): void
    {
        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo') && !$request->boolean('include_merged')) {
            $query->where(function ($q) {
                $q->whereNull('a.MergedIntoNo')->orWhere('a.MergedIntoNo', 0);
            });
        }

        if ($request->filled('department_id')) {
            $query->whereRaw('COALESCE(s.BolumAdiNo, a.BolumAdiNo) = ?', [intval($request->department_id)]);
        }

        if ($request->filled('component_type')) {
            $query->where('a.UrunCesidi', $request->component_type);
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $numericSearch = ctype_digit($s) ? intval($s) : null;
            $numericLike = "%{$s}%";

            $query->where(function ($q) use ($s, $numericSearch, $numericLike) {
                $q->where('a.AraUrunAdi', 'like', "%$s%")
                  ->orWhere('b.BolumAdi', 'like', "%$s%");

                if ($numericSearch !== null) {
                    $q->orWhere('s.No', $numericSearch)
                      ->orWhere('a.No', $numericSearch)
                      ->orWhere('s.AraUrunAdiNo', $numericSearch)
                      ->orWhere('s.UrunIDNo', $numericSearch)
                      ->orWhereRaw('CAST(s.No AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(a.No AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(s.AraUrunAdiNo AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(s.UrunIDNo AS CHAR) LIKE ?', [$numericLike]);
                }
            });
        }

        $stockStatus = (string) $request->input('stock_status', $request->input('status', ''));
        if ($stockStatus === 'zero') {
            $query->whereRaw('COALESCE(s.Adet, 0) = 0');
        } elseif ($stockStatus === 'nonzero') {
            $query->whereRaw('COALESCE(s.Adet, 0) <> 0');
        }
    }

    private function applyStockSort($query, string $sortField, string $sortDir): void
    {
        $map = [
            'BolumAdi' => DB::raw("IFNULL(b.BolumAdi,'')"),
            'AraUrunAdi' => DB::raw("IFNULL(a.AraUrunAdi,'')"),
            'Adet' => DB::raw('COALESCE(s.Adet, 0)'),
            'TamponMiktar' => DB::raw('COALESCE(s.TamponMiktar, 0)'),
        ];

        if ($sortField === 'No') {
            $query
                ->orderByRaw('CASE WHEN s.No IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('s.No', $sortDir)
                ->orderBy('a.No', 'asc');
            return;
        }

        $query
            ->orderBy($map[$sortField] ?? DB::raw('COALESCE(s.No, 2147483647)'), $sortDir)
            ->orderBy('a.No', 'asc');
    }

    private function attachSpecialProductionCapacityToStocks($stocks): void
    {
        if (!$stocks || $stocks->isEmpty()) {
            return;
        }

        foreach ($stocks as $stock) {
            $stock->UretimToplamAdet = 0;
            $stock->UretimRezerveAdet = 0;
            $stock->UretimMusaitAdet = 0;
        }

        if (!Schema::hasTable('tbSiparisSatir')) {
            return;
        }

        $finalProductNames = $stocks
            ->filter(fn ($stock) => $this->stockProductionMatchType((string) ($stock->UrunCesidi ?? '')) === 'Nihai')
            ->map(fn ($stock) => trim((string) ($stock->AraUrunAdi ?? '')))
            ->filter()
            ->unique()
            ->values();

        $finalProductNoByName = collect();
        if ($finalProductNames->isNotEmpty() && Schema::hasTable('tbUrunler')) {
            $finalProductNoByName = DB::table('tbUrunler')
                ->whereIn('UrunID', $finalProductNames->all())
                ->pluck('No', 'UrunID');
        }

        $pairs = [];
        $rowKeys = [];
        foreach ($stocks as $index => $stock) {
            $matchType = $this->stockProductionMatchType((string) ($stock->UrunCesidi ?? ''));
            if ($matchType === '') {
                continue;
            }

            $componentNo = intval($stock->AraUrunAdiNo ?? 0);
            $matchNo = $componentNo;
            if ($matchType === 'Nihai') {
                $name = trim((string) ($stock->AraUrunAdi ?? ''));
                $matchNo = intval($finalProductNoByName[$name] ?? 0) ?: $componentNo;
            }

            $key = $this->stockProductionCapacityKey($matchNo, $matchType);
            if ($key === '') {
                continue;
            }

            $pairs[] = ['no' => $matchNo, 'type' => $matchType, 'key' => $key];
            $rowKeys[$index] = $key;
        }

        $capacityMap = $this->buildStockSpecialProductionCapacityMap($pairs);
        if (empty($capacityMap)) {
            return;
        }

        foreach ($stocks as $index => $stock) {
            $key = $rowKeys[$index] ?? '';
            if ($key === '' || !isset($capacityMap[$key])) {
                continue;
            }

            $capacity = $capacityMap[$key];
            $stock->UretimToplamAdet = intval($capacity['total'] ?? 0);
            $stock->UretimRezerveAdet = intval($capacity['reserved'] ?? 0);
            $stock->UretimMusaitAdet = intval($capacity['available'] ?? 0);
        }
    }

    private function buildStockSpecialProductionCapacityMap(array $pairs): array
    {
        $pairs = collect($pairs)
            ->filter(fn ($pair) => intval($pair['no'] ?? 0) > 0 && trim((string) ($pair['type'] ?? '')) !== '')
            ->unique('key')
            ->values();

        if ($pairs->isEmpty() || !Schema::hasTable('tbSiparisSatir')) {
            return [];
        }

        $productionRows = DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where(function ($query) {
                $query->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
                    ->orWhere('sp.Musteri', 'like', 'OZEL URETIM%');
            })
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->where(function ($query) {
                $query->whereRaw('IFNULL(sp.GorevNo, 0) > 0')
                    ->orWhereIn('sp.Durum', ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor']);
            })
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($pairQuery) use ($pair) {
                        $pairQuery->where('sp.EslesenUrunNo', intval($pair['no']))
                            ->where('sp.EslesenUrunTur', (string) $pair['type']);
                    });
                }
            })
            ->get([
                'sp.No',
                'sp.EslesenUrunNo',
                'sp.EslesenUrunTur',
                'sp.Adet',
            ]);

        if ($productionRows->isEmpty()) {
            return [];
        }

        $productionNos = $productionRows
            ->pluck('No')
            ->map(fn ($no) => intval($no))
            ->filter(fn ($no) => $no > 0)
            ->values()
            ->all();

        $reservedMap = DB::table('tbSiparisSatir')
            ->whereIn('BagliOlduguOzelUretimNo', $productionNos)
            ->where('Aktif', 1)
            ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi'])
            ->select('BagliOlduguOzelUretimNo', DB::raw('SUM(Adet) as ReservedAdet'))
            ->groupBy('BagliOlduguOzelUretimNo')
            ->pluck('ReservedAdet', 'BagliOlduguOzelUretimNo');

        $capacity = [];
        foreach ($productionRows as $row) {
            $key = $this->stockProductionCapacityKey(
                intval($row->EslesenUrunNo ?? 0),
                (string) ($row->EslesenUrunTur ?? '')
            );
            if ($key === '') {
                continue;
            }

            $total = intval($row->Adet ?? 0);
            $reserved = intval($reservedMap[intval($row->No ?? 0)] ?? 0);
            if (!isset($capacity[$key])) {
                $capacity[$key] = [
                    'total' => 0,
                    'reserved' => 0,
                    'available' => 0,
                ];
            }

            $capacity[$key]['total'] += $total;
            $capacity[$key]['reserved'] += $reserved;
            $capacity[$key]['available'] += max(0, $total - $reserved);
        }

        return $capacity;
    }

    private function stockProductionMatchType(string $type): string
    {
        $type = trim($type);
        $lower = strtolower($type);

        if ($type === 'HamMadde' || str_contains($lower, 'ham')) {
            return 'HamMadde';
        }

        if ($type === 'Ara' || str_contains($lower, 'ara') || str_contains($lower, 'mamul') || str_contains($lower, 'mamül') || str_contains($lower, 'bile')) {
            return 'Ara';
        }

        if ($type === 'Nihai' || str_contains($lower, 'nihai') || str_contains($lower, 'nihayi')) {
            return 'Nihai';
        }

        return '';
    }

    private function stockProductionCapacityKey(int $productNo, string $productType): string
    {
        $productType = trim($productType);
        if ($productNo <= 0 || $productType === '') {
            return '';
        }

        return $productType . ':' . $productNo;
    }

    private function applyMovementFilters($query, Request $request): void
    {
        if ($request->filled('movement_type')) {
            $query->where('m.movement_type', $request->input('movement_type'));
        }

        if ($request->filled('direction')) {
            $query->where('m.direction', $request->input('direction'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('m.happened_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('m.happened_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('m.title_human', 'like', '%' . $q . '%')
                    ->orWhere('m.description', 'like', '%' . $q . '%')
                    ->orWhere('m.actor_name', 'like', '%' . $q . '%')
                    ->orWhere('m.order_no', 'like', '%' . $q . '%')
                    ->orWhere('m.source_id', 'like', '%' . $q . '%')
                    ->orWhere('a.AraUrunAdi', 'like', '%' . $q . '%')
                    ->orWhere('b.BolumAdi', 'like', '%' . $q . '%');

                if (ctype_digit($q)) {
                    $subQuery->orWhere('m.order_item_no', intval($q))
                        ->orWhere('m.work_order_no', intval($q))
                        ->orWhere('m.personnel_task_no', intval($q))
                        ->orWhere('m.stock_row_no', intval($q));
                }
            });
        }
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Ekstre kaydı ana stok işlemini engellemesin.
        }
    }

    private function requestHasAny(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->has($key)) {
                return true;
            }
        }

        return false;
    }

    private function bufferPreservingReservedQuantity(?object $record, int $newQuantity): int
    {
        if (!$record) {
            return $newQuantity;
        }

        $oldQuantity = max(0, intval($record->Adet ?? 0));
        $oldFree = $this->clampStockBuffer($oldQuantity, intval($record->TamponMiktar ?? 0));
        $oldReserved = max(0, $oldQuantity - $oldFree);

        return $this->clampStockBuffer($newQuantity, $newQuantity - $oldReserved);
    }

    private function clampStockBuffer(int $quantity, int $buffer): int
    {
        return max(0, min(max(0, $quantity), $buffer));
    }

    private function buildStockWorkbook($stocks): string
    {
        $rows = [[
            'No',
            'Bölüm',
            'Ara Ürün',
            'Ürün Çeşidi',
            'Adet',
            'Görevdeki',
            'Boşta/Tampon',
        ]];

        foreach ($stocks as $row) {
            $adet = intval($row->Adet);
            $bostaTampon = max(0, min($adet, intval($row->TamponMiktar)));
            $gorevdeki = max(0, $adet - $bostaTampon);

            $rows[] = [
                $row->No === null ? null : intval($row->No),
                (string) $row->BolumAdi,
                (string) $row->AraUrunAdi,
                (string) $row->UrunCesidi,
                $adet,
                $gorevdeki,
                $bostaTampon,
            ];
        }

        return $this->buildXlsxWorkbook('Stoklar', $rows, [11, 22, 48, 18, 12, 14, 16]);
    }

    private function buildXlsxWorkbook(string $sheetName, array $rows, array $columnWidths): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Sunucuda Excel dosyası oluşturmak için ZipArchive eklentisi gerekli.');
        }

        $rows = array_values($rows);
        if (count($rows) === 1) {
            $rows[] = array_fill(0, count($rows[0]), null);
        }

        $columnCount = max(1, ...array_map(fn ($row) => max(1, count($row)), $rows));
        foreach ($rows as $index => $row) {
            $rows[$index] = array_pad(array_values($row), $columnCount, null);
        }

        $sheetName = $this->safeExcelSheetName($sheetName);
        $lastColumn = $this->excelColumnName($columnCount);
        $lastRow = count($rows);
        $tableRef = 'A1:' . $lastColumn . $lastRow;

        $tmp = tempnam(sys_get_temp_dir(), 'stocks_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Excel dosyası için geçici alan oluşturulamadı.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Excel dosyası oluşturulamadı.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildXlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildXlsxRootRelsXml());
        $zip->addFromString('docProps/core.xml', $this->buildXlsxCoreXml());
        $zip->addFromString('docProps/app.xml', $this->buildXlsxAppXml($sheetName));
        $zip->addFromString('xl/workbook.xml', $this->buildXlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildXlsxWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildXlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildXlsxWorksheetXml($rows, $columnWidths, $tableRef));
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->buildXlsxWorksheetRelsXml());
        $zip->addFromString('xl/tables/table1.xml', $this->buildXlsxTableXml($rows[0], $tableRef));
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        if ($content === false) {
            throw new \RuntimeException('Excel dosyası okunamadı.');
        }

        return $content;
    }

    private function readStockImportRows($file): array
    {
        $path = $file->getRealPath();
        if (!$path || !is_file($path)) {
            throw new \RuntimeException('Dosya okunamadı.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $probe = file_get_contents($path, false, null, 0, 512);
        $probe = $probe === false ? '' : ltrim($probe, "\xEF\xBB\xBF\r\n\t ");

        if (str_starts_with($probe, 'PK')) {
            return $this->readXlsxRows($path);
        }

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($path);
        }

        if ($extension === 'xls' || str_starts_with(strtolower($probe), '<')) {
            return $this->readHtmlTableRows($path);
        }

        return $this->readCsvRows($path);
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('CSV dosyası okunamadı.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new \RuntimeException('Geçersiz CSV formatı.');
            }

            $delimiter = $this->detectCsvDelimiter($firstLine);
            $rows = [str_getcsv($firstLine, $delimiter)];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readHtmlTableRows(string $path): array
    {
        $html = file_get_contents($path);
        if ($html === false || trim($html) === '') {
            throw new \RuntimeException('Excel tablosu okunamadı.');
        }

        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;

        if (!class_exists(\DOMDocument::class)) {
            return $this->readHtmlTableRowsWithRegex($html);
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->readHtmlTableRowsWithRegex($html);
        }

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            throw new \RuntimeException('Excel tablosu bulunamadı.');
        }

        $rows = [];
        foreach ($tables->item(0)->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE || !in_array(strtolower($cell->nodeName), ['th', 'td'], true)) {
                    continue;
                }
                $row[] = $this->normalizeSpreadsheetText($cell->textContent);
            }
            if ($this->spreadsheetRowHasContent($row)) {
                $rows[] = $row;
            }
        }

        if (!$rows) {
            throw new \RuntimeException('Excel tablosunda okunacak satır bulunamadı.');
        }

        return $rows;
    }

    private function readHtmlTableRowsWithRegex(string $html): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);
        $rows = [];

        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $rowHtml, $cellMatches);
            $row = array_map(function ($cellHtml) {
                return $this->normalizeSpreadsheetText(html_entity_decode(strip_tags($cellHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }, $cellMatches[1]);

            if ($this->spreadsheetRowHasContent($row)) {
                $rows[] = $row;
            }
        }

        if (!$rows) {
            throw new \RuntimeException('Excel tablosunda okunacak satır bulunamadı.');
        }

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class) || !class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('Excel çalışma kitabını okumak için ZipArchive ve DOM eklentileri gerekli.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Excel çalışma kitabı açılamadı.');
        }

        try {
            $sharedStrings = $this->readXlsxSharedStrings($zip);
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false && $sheetPath !== 'xl/worksheets/sheet1.xml') {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            }

            if ($sheetXml === false) {
                throw new \RuntimeException('Excel çalışma kitabında ilk sayfa bulunamadı.');
            }

            $dom = $this->loadXmlDocument($sheetXml);
            $rows = [];

            foreach ($dom->getElementsByTagName('row') as $rowNode) {
                $cells = [];

                foreach ($rowNode->childNodes as $cellNode) {
                    if ($cellNode->nodeType !== XML_ELEMENT_NODE || strtolower($cellNode->localName) !== 'c') {
                        continue;
                    }

                    $cellRef = $cellNode->getAttribute('r');
                    $columnIndex = $cellRef ? $this->excelColumnIndexFromCellReference($cellRef) : count($cells) + 1;
                    $cells[$columnIndex - 1] = $this->readXlsxCellValue($cellNode, $sharedStrings);
                }

                if ($cells) {
                    ksort($cells);
                    $maxIndex = max(array_keys($cells));
                    $row = [];
                    for ($i = 0; $i <= $maxIndex; $i++) {
                        $row[] = $cells[$i] ?? '';
                    }
                    $rows[] = $row;
                }
            }

            if (!$rows) {
                throw new \RuntimeException('Excel çalışma kitabında okunacak satır bulunamadı.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function readXlsxSharedStrings($zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $dom = $this->loadXmlDocument($xml);
        $strings = [];

        foreach ($dom->getElementsByTagName('si') as $item) {
            $strings[] = $this->collectXlsxText($item);
        }

        return $strings;
    }

    private function resolveFirstWorksheetPath($zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $dom = $this->loadXmlDocument($workbookXml);
        $sheet = $dom->getElementsByTagName('sheet')->item(0);
        if (!$sheet) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationshipId = $sheet->getAttribute('r:id')
            ?: $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');

        if (!$relationshipId) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationships = $this->readXlsxRelationships($zip, 'xl/_rels/workbook.xml.rels');
        if (!isset($relationships[$relationshipId])) {
            return 'xl/worksheets/sheet1.xml';
        }

        return $this->normalizeZipTarget('xl', $relationships[$relationshipId]);
    }

    private function readXlsxRelationships($zip, string $path): array
    {
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return [];
        }

        $dom = $this->loadXmlDocument($xml);
        $relationships = [];

        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
            $id = $relationship->getAttribute('Id');
            $target = $relationship->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $relationships[$id] = $target;
            }
        }

        return $relationships;
    }

    private function readXlsxCellValue($cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            return $this->normalizeSpreadsheetText($this->collectXlsxText($cell));
        }

        $value = $this->firstXlsxChildText($cell, 'v');

        if ($type === 's') {
            return $this->normalizeSpreadsheetText($sharedStrings[intval($value)] ?? '');
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $this->normalizeSpreadsheetText($value);
    }

    private function collectXlsxText($node): string
    {
        $parts = [];
        foreach ($node->getElementsByTagName('t') as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return implode('', $parts);
    }

    private function firstXlsxChildText($node, string $tagName): string
    {
        $child = $node->getElementsByTagName($tagName)->item(0);
        return $child ? $child->textContent : '';
    }

    private function loadXmlDocument(string $xml)
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Excel çalışma kitabı XML içeriği okunamadı.');
        }

        return $dom;
    }

    private function spreadsheetRowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null) {
                continue;
            }

            if (is_string($cell)) {
                if (trim(str_replace("\xc2\xa0", ' ', $cell)) !== '') {
                    return true;
                }
                continue;
            }

            return true;
        }

        return false;
    }

    private function normalizeSpreadsheetText(mixed $value): string
    {
        $text = str_replace("\xc2\xa0", ' ', (string) ($value ?? ''));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function buildXlsxWorksheetXml(array $rows, array $columnWidths, string $tableRef): string
    {
        $columnCount = count($rows[0]);
        $columnsXml = '';
        for ($i = 1; $i <= $columnCount; $i++) {
            $width = $columnWidths[$i - 1] ?? 18;
            $columnsXml .= '<col min="' . $i . '" max="' . $i . '" width="' . number_format((float) $width, 2, '.', '') . '" customWidth="1"/>';
        }

        $sheetDataXml = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $rowAttributes = ' r="' . $rowNumber . '" spans="1:' . $columnCount . '"';
            if ($rowIndex === 0) {
                $rowAttributes .= ' ht="22" customHeight="1"';
            }

            $cellsXml = '';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->excelColumnName($columnIndex + 1) . $rowNumber;
                $style = $rowIndex === 0 ? 1 : ((is_int($value) || is_float($value)) ? 2 : 3);
                $cellsXml .= $this->buildXlsxCellXml($cellRef, $value, $style);
            }

            $sheetDataXml .= '<row' . $rowAttributes . '>' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $tableRef . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols>' . $columnsXml . '</cols>'
            . '<sheetData>' . $sheetDataXml . '</sheetData>'
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '<tableParts count="1"><tablePart r:id="rId1"/></tableParts>'
            . '</worksheet>';
    }

    private function buildXlsxCellXml(string $cellRef, mixed $value, int $style): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $this->xmlEscape((string) $value) . '</t></is></c>';
    }

    private function buildXlsxTableXml(array $headers, string $tableRef): string
    {
        $seen = [];
        $columnsXml = '';

        foreach ($headers as $index => $header) {
            $name = trim((string) $header);
            if ($name === '') {
                $name = 'Sütun ' . ($index + 1);
            }

            $baseName = $name;
            $suffix = 2;
            while (isset($seen[$name])) {
                $name = $baseName . ' ' . $suffix;
                $suffix++;
            }
            $seen[$name] = true;

            $columnsXml .= '<tableColumn id="' . ($index + 1) . '" name="' . $this->xmlEscape($name) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="Stoklar" displayName="Stoklar" ref="' . $tableRef . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $tableRef . '"/>'
            . '<tableColumns count="' . count($headers) . '">' . $columnsXml . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/>'
            . '</table>';
    }

    private function buildXlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>'
            . '</Types>';
    }

    private function buildXlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function buildXlsxWorkbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="23426"/>'
            . '<workbookPr defaultThemeVersion="164011"/>'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function buildXlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function buildXlsxWorksheetRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/>'
            . '</Relationships>';
    }

    private function buildXlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2F8F83"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2E0"/></left><right style="thin"><color rgb="FFD9E2E0"/></right><top style="thin"><color rgb="FFD9E2E0"/></top><bottom style="thin"><color rgb="FFD9E2E0"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="1" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    private function buildXlsxCoreXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>zemuretim</dc:creator>'
            . '<cp:lastModifiedBy>zemuretim</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function buildXlsxAppXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xmlEscape($sheetName) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    private function safeExcelSheetName(string $sheetName): string
    {
        $sheetName = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', trim($sheetName)) ?? 'Sayfa1';
        $sheetName = $sheetName === '' ? 'Sayfa1' : $sheetName;

        return function_exists('mb_substr') ? mb_substr($sheetName, 0, 31) : substr($sheetName, 0, 31);
    }

    private function xmlEscape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name ?: 'A';
    }

    private function excelColumnIndexFromCellReference(string $cellReference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return 1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(1, $index);
    }

    private function normalizeZipTarget(string $baseDir, string $target): string
    {
        $path = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : trim($baseDir, '/') . '/' . $target;

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function detectCsvDelimiter(string $line): string
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        $delimiters = [',' => 0, ';' => 0, "\t" => 0];

        foreach ($delimiters as $delimiter => $_) {
            $delimiters[$delimiter] = count(str_getcsv($line, $delimiter));
        }

        arsort($delimiters);
        $detected = array_key_first($delimiters);

        return $detected ?: ',';
    }

    private function findCsvHeaderIndex(array $header, array $aliases): int|false
    {
        $normalized = array_map(fn ($h) => $this->normalizeCsvHeader((string) $h), $header);

        foreach ($aliases as $alias) {
            $needle = $this->normalizeCsvHeader($alias);
            $idx = array_search($needle, $normalized, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return false;
    }

    private function normalizeCsvHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ş' => 's', 'ş' => 's',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function parseCsvInteger(mixed $value): int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 0;
        }

        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        if (is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        $digits = preg_replace('/[^\d-]+/', '', $raw);
        return $digits === '' ? 0 : (int) $digits;
    }
}
