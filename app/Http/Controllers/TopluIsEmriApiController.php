<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TopluIsEmriApi.ashx → Laravel Controller
 * 3 endpoint: getProducts, createWorkOrders, getSchema
 */
class TopluIsEmriApiController extends Controller
{
    public function __construct(
        protected \App\Services\WorkOrderService $workOrderService
    ) {}

    public function handleEndpoint(Request $request)
    {
        $action = $request->query('action', '');
        try {
            return match ($action) {
                'getProducts' => $this->getProducts(),
                'createWorkOrders' => $this->createWorkOrders($request),
                'getSchema' => $this->getSchema(),
                default => response()->json(['success' => false, 'message' => 'action=getProducts veya createWorkOrders gerekli']),
            };
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Hata: ' . $e->getMessage(), 'stack' => $e->getTraceAsString()]);
        }
    }

    private function getProducts()
    {
        $products = [];

        // Nihai Ürünler
        $nihai = DB::select("SELECT No, IFNULL(UrunID,'') AS UrunID, IFNULL(AraAdlarYol,'') AS AraAdlarYol, IFNULL(SistemAdi,'') AS SistemAdi, IFNULL(SistemKodu,'') AS SistemKodu FROM tbUrunler ORDER BY UrunID");
        foreach ($nihai as $r) {
            $products[] = [
                'no' => (string)$r->No,
                'urunId' => trim($r->UrunID),
                'araAdlarYol' => trim($r->AraAdlarYol),
                'sistemAdi' => trim($r->SistemAdi),
                'sistemKodu' => trim($r->SistemKodu),
                'tur' => 'Nihai',
            ];
        }

        // Ara Ürünler
        $ara = DB::select("SELECT No, IFNULL(AraUrunAdi,'') AS AraUrunAdi, IFNULL(Performans,0) AS Performans FROM tbAraUrun ORDER BY AraUrunAdi");
        foreach ($ara as $r) {
            $products[] = [
                'no' => (string)$r->No,
                'urunId' => trim($r->AraUrunAdi),
                'performans' => (string)$r->Performans,
                'tur' => 'Ara',
            ];
        }

        return response()->json(['success' => true, 'count' => count($products), 'products' => $products]);
    }

    private function createWorkOrders(Request $request)
    {
        $orders = $request->json()->all();
        if (empty($orders)) {
            return response()->json(['success' => false, 'message' => 'Siparis verisi bulunamadi.']);
        }

        $created = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $urunAdi = $order['urunAdi'] ?? '';
                $adet = intval($order['adet'] ?? 0);
                $matchedDbId = $order['matchedDbId'] ?? '';
                $matchType = $order['matchType'] ?? '';

                if (empty($matchedDbId) || $adet <= 0) {
                    $failed++;
                    $errors[] = 'Eslestirme yok: ' . $urunAdi;
                    continue;
                }

                $matchedId = intval($matchedDbId);

                if ($matchType === 'Nihai') {
                    $result = $this->workOrderService->createWorkOrderForProduct($matchedId, $adet, 'StokHaric', $urunAdi);
                } else {
                    $result = $this->workOrderService->createWorkOrderForComponent($matchedId, $adet, 'StokHaric');
                }

                if (!($result['success'] ?? false)) {
                    throw new \RuntimeException($result['message'] ?? 'İş emri oluşturulamadı.');
                }

                $created++;
            } catch (\Exception $ex) {
                $failed++;
                $errors[] = ($order['urunAdi'] ?? 'Bilinmiyor') . ': ' . $ex->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
            'message' => $created . ' is emri olusturuldu. ' . $failed . ' hata.',
        ]);
    }

    private function getSchema()
    {
        $columns = DB::select("SELECT COLUMN_NAME as name, DATA_TYPE as type, IS_NULLABLE as nullable, CHARACTER_MAXIMUM_LENGTH as maxLength FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tbGorevler' ORDER BY ORDINAL_POSITION");

        return response()->json([
            'success' => true,
            'table' => 'tbGorevler',
            'columnCount' => count($columns),
            'columns' => $columns,
        ]);
    }
}
