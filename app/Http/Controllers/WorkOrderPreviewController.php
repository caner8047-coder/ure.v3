<?php

namespace App\Http\Controllers;

use App\Services\AppSettingService;
use App\Services\WorkOrderBomPreviewService;
use Illuminate\Http\Request;

class WorkOrderPreviewController extends Controller
{
    private const SETTING_KEY = 'work_order_bom_preview_enabled';

    public function settings(AppSettingService $settings)
    {
        return response()->json([
            'success' => true,
            'enabled' => $settings->bool(self::SETTING_KEY, true),
        ]);
    }

    public function updateSettings(Request $request, AppSettingService $settings)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $settings->set(self::SETTING_KEY, (bool) $validated['enabled'], 'boolean');

        return response()->json([
            'success' => true,
            'enabled' => (bool) $validated['enabled'],
            'message' => (bool) $validated['enabled']
                ? 'İş emri BOM önizlemesi açıldı.'
                : 'İş emri BOM önizlemesi kapatıldı.',
        ]);
    }

    public function preview(Request $request, WorkOrderBomPreviewService $previewService, AppSettingService $settings)
    {
        if (!$settings->bool(self::SETTING_KEY, true)) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'groups' => [],
            ]);
        }

        $mode = (string) $request->input('mode', 'manual');

        if ($mode === 'manual') {
            $result = $previewService->buildManualPreview(
                (int) $request->input('urunNo', 0),
                (string) $request->input('tur', 'Nihai'),
                (int) $request->input('adet', 0),
                (string) $request->input('stokDurum', 'StokDahil')
            );
        } else {
            $result = $previewService->buildOrderPreview(
                (array) $request->input('satirNolar', []),
                (int) $request->input('surplus', 0),
                (string) $request->input('tur', 'Nihai'),
                $request->filled('altBilesenNo') ? (int) $request->input('altBilesenNo') : null,
                (string) $request->input('stokDurum', 'StokDahil')
            );
        }

        $result['enabled'] = true;

        return response()->json($result);
    }
}
