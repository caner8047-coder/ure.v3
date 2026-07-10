<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use App\Services\OrderQueryService;
use App\Http\Controllers\Order\OrderMatchController;
use App\Http\Controllers\Order\OrderStockController;
use App\Http\Controllers\Order\OrderSpecialProductionController;
use App\Http\Controllers\Order\OrderWorkOrderController;

class SiparisApiController extends Controller
{
    private const MUTATING_ACTIONS = [
        'uploadOrders',
        'matchProduct',
        'clearOrderMatch',
        'createOrderWorkOrders',
        'createManualWorkOrder',
        'passivateWithWorkOrderCancel',
        'saveStockCodes',
        'cancelWorkOrder',
        'cancelBulkWorkOrders',
        'createIndependentStockOrder',
        'addIndependentStockOrder',
        'importPersoneller',
        'importBolumler',
        'importAraUrunler',
        'importUrunler',
        'clearAllOrders',
        'deductStock',
        'deductStockBulk',
        'undoDeductStock',
        'saveThreshold',
        'deleteThreshold',
        'resetThresholds',
        'rematchOrders',
        'addMatchCache',
        'deleteMatchCache',
        'addSetDefinition',
        'deleteSetDefinition',
        'linkOrderToSpecialProduction',
        'linkOrdersToSpecialProductionBulk',
        'cancelWipAllocation',
        'onlyUpdateStatusBulk',
        'fixKayipOzelUretim',
        'reactivateOrder',
    ];

    public function __construct(
        protected OrderQueryService $orderQuery
    ) {}

    /**
     * Handles all legacy ?action= AJAX requests seamlessly
     */
    public function handleEndpoint(Request $request)
    {
        $action = (string) $request->input('action', '');

        if ($guardResponse = $this->guardLegacyRequest($request, $action)) {
            return $guardResponse;
        }

        try {
            switch ($action) {
                case 'uploadOrders':
                    return response()->json($this->orderQuery->uploadOrders($request));
                case 'getOrders':
                    return response()->json($this->orderQuery->getOrders($request));
                case 'matchProduct':
                    return app(OrderMatchController::class)->matchProduct($request);
                case 'clearOrderMatch':
                    return app(OrderMatchController::class)->clearOrderMatch($request);
                case 'createOrderWorkOrders':
                    return app(OrderWorkOrderController::class)->createOrderWorkOrders($request);
                case 'createManualWorkOrder':
                    return app(OrderWorkOrderController::class)->createManualWorkOrder($request);
                case 'passivateWithWorkOrderCancel':
                    return app(OrderWorkOrderController::class)->passivateWithWorkOrderCancel($request);
                case 'saveStockCodes':
                    return app(OrderStockController::class)->saveStockCodes($request);
                case 'getSummary':
                    return response()->json($this->orderQuery->getSummary($request));
                case 'getProducts':
                    return response()->json($this->orderQuery->getProductsList($request));
                case 'cancelWorkOrder':
                    return app(OrderWorkOrderController::class)->cancelWorkOrder($request);
                case 'cancelBulkWorkOrders':
                    return app(OrderWorkOrderController::class)->cancelBulkWorkOrders($request);
                case 'getPersoneller':
                    return response()->json($this->orderQuery->getPersoneller($request));
                case 'getBolumler':
                    return response()->json($this->orderQuery->getBolumler($request));
                case 'getAraUrunler':
                    return response()->json($this->orderQuery->getAraUrunler($request));
                case 'getUrunler':
                    return response()->json($this->orderQuery->getUrunler($request));
                case 'getProductBomComponents':
                    return response()->json($this->orderQuery->getProductBomComponents($request));
                case 'createIndependentStockOrder':
                    return response()->json($this->orderQuery->createIndependentStockOrder($request));
                case 'addIndependentStockOrder':
                    return response()->json($this->orderQuery->addIndependentStockOrder($request));
                case 'importPersoneller':
                    return $this->importPersoneller($request);
                case 'importBolumler':
                    return $this->importBolumler($request);
                case 'importAraUrunler':
                    return $this->importAraUrunler($request);
                case 'importUrunler':
                    return $this->importUrunler($request);
                case 'clearAllOrders':
                    return response()->json($this->orderQuery->clearAllOrders($request));
                case 'deductStock':
                    return app(OrderStockController::class)->deductStock($request);
                case 'deductStockBulk':
                    return app(OrderStockController::class)->deductStockBulk($request);
                case 'undoDeductStock':
                    return app(OrderStockController::class)->undoDeductStock($request);
                case 'getThresholds':
                    return app(OrderStockController::class)->getThresholds($request);
                case 'saveThreshold':
                    return app(OrderStockController::class)->saveThreshold($request);
                case 'deleteThreshold':
                    return app(OrderStockController::class)->deleteThreshold($request);
                case 'getCriticalStockAlerts':
                    return app(OrderStockController::class)->getCriticalStockAlerts($request);
                case 'resetThresholds':
                    return app(OrderStockController::class)->resetThresholds($request);
                case 'rematchOrders':
                    return app(OrderMatchController::class)->rematchOrders($request);
                case 'getMatchCache':
                    return app(OrderMatchController::class)->getMatchCache($request);
                case 'addMatchCache':
                    return app(OrderMatchController::class)->addMatchCache($request);
                case 'deleteMatchCache':
                    return app(OrderMatchController::class)->deleteMatchCache($request);
                case 'getSetDefinitions':
                    return response()->json($this->orderQuery->getSetDefinitions($request));
                case 'addSetDefinition':
                    return response()->json($this->orderQuery->addSetDefinition($request));
                case 'deleteSetDefinition':
                    return response()->json($this->orderQuery->deleteSetDefinition($request));
                case 'getWorkOrderHistory':
                    return app(OrderWorkOrderController::class)->getWorkOrderHistory($request);
                case 'getAvailableSpecialProductions':
                    return app(OrderSpecialProductionController::class)->getAvailableSpecialProductions($request);
                case 'linkOrderToSpecialProduction':
                    return app(OrderSpecialProductionController::class)->linkOrderToSpecialProduction($request);
                case 'linkOrdersToSpecialProductionBulk':
                    return app(OrderSpecialProductionController::class)->linkOrdersToSpecialProductionBulk($request);
                case 'cancelWipAllocation':
                    return app(OrderSpecialProductionController::class)->cancelWipAllocation($request);
                case 'onlyUpdateStatusBulk':
                    return app(OrderWorkOrderController::class)->onlyUpdateStatusBulk($request);
                case 'fixKayipOzelUretim':
                    return app(OrderWorkOrderController::class)->fixKayipOzelUretim($request);
                case 'getPasifDevamEden':
                    return app(OrderWorkOrderController::class)->getPasifDevamEden($request);
                case 'getOrderPipeline':
                    return app(OrderWorkOrderController::class)->getOrderPipeline($request);
                case 'getProductionDetail':
                    return app(OrderWorkOrderController::class)->getProductionDetail($request);
                case 'reactivateOrder':
                    return app(OrderWorkOrderController::class)->reactivateOrder($request);
                default:
                    return response()->json(['success' => false, 'message' => "Geçersiz action parametresi: {$action}"]);
            }
        } catch (Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Hata: ' . $ex->getMessage(),
                'stack' => config('app.debug') ? $ex->getTraceAsString() : null
            ]);
        }
    }

    private function guardLegacyRequest(Request $request, string $action)
    {
        $isMutatingAction = in_array($action, self::MUTATING_ACTIONS, true);

        if ($isMutatingAction && !$request->isMethod('POST')) {
            return response()->json([
                'success' => false,
                'message' => "Bu işlem (action={$action}) sadece POST isteği ile gerçekleştirilebilir."
            ], 405);
        }

        if ($isMutatingAction && !$this->hasTrustedOrigin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Güvenilmeyen kaynak (CSRF/CORS engeli).'
            ], 403);
        }

        return null;
    }

    private function hasTrustedOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!$source) {
            return true;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        if (!$sourceHost) {
            return false;
        }

        $sourcePort = parse_url($source, PHP_URL_PORT);
        $sourceHostWithPort = strtolower($sourceHost . ($sourcePort ? ':' . $sourcePort : ''));
        $requestHost = strtolower($request->getHost());
        $requestHostWithPort = strtolower($request->getHttpHost());

        return $sourceHostWithPort === $requestHostWithPort || strtolower($sourceHost) === $requestHost;
    }

    private function importPersoneller(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'personnel');
    }

    private function importBolumler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'departments');
    }

    private function importAraUrunler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'components');
    }

    private function importUrunler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'products');
    }

    private function importLegacyDatabaseRows(Request $request, string $module)
    {
        $payload = $request->json()->all();
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : $payload;

        $request->merge(['rows' => is_array($rows) ? $rows : []]);

        return app(\App\Http\Controllers\Admin\AdminImportController::class)->importModule($request, $module);
    }
}
