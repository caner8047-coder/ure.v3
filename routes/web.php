<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AIChatController;
use App\Http\Controllers\SiparisApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StocksController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\Admin\AdminDepartmentController;
use App\Http\Controllers\Admin\AdminPersonnelController;
use App\Http\Controllers\Admin\AdminComponentController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminPoolTaskController;
use App\Http\Controllers\Admin\AdminImportController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Panel\PanelTaskController;
use App\Http\Controllers\Panel\PanelAvailableTaskController;
use App\Http\Controllers\Panel\PanelReportController;
use App\Http\Controllers\Panel\PanelMessageController;
use App\Http\Controllers\Panel\PanelDependencyController;
use App\Http\Controllers\TopluIsEmriApiController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\WorkOrderCenterController;
use App\Http\Controllers\WorkOrderPreviewController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\ForecastController;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route('dashboard');
});

// ===== API Endpoints (Backward-compatible) =====
Route::any('/SiparisApi.ashx', [SiparisApiController::class, 'handleEndpoint'])
    ->middleware(['auth', 'admin'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::any('/TopluIsEmriApi.ashx', [TopluIsEmriApiController::class, 'handleEndpoint'])
    ->middleware(['auth', 'admin'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

// ===== Auth Routes =====
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ===== Legacy WebForms URL Aliases =====
Route::get('/AnaSayfa.aspx', [AuthController::class, 'showLoginForm'])->name('legacy.login');
Route::post('/AnaSayfa.aspx', [AuthController::class, 'login']);
Route::get('/logout.aspx', [AuthController::class, 'logout'])->name('legacy.logout');
Route::get('/SifremiUnuttum.aspx', function () { return redirect()->route('password.request'); });
Route::post('/SifremiUnuttum.aspx', [AuthController::class, 'forgotPassword']);
Route::get('/Hakkimizda.aspx', function () { return redirect()->route('pages.about'); });
Route::get('/iletisimMisafir.aspx', function () { return redirect()->route('pages.contact'); });
Route::get('/Resimler/{filename}', function (string $filename) {
    $safeName = str_replace('\\', '/', rawurldecode($filename));
    if (str_contains($safeName, '..')) {
        abort(404);
    }

    $imageKey = static function (string $value): string {
        $key = trim($value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key);
            if ($converted !== false) {
                $key = $converted;
            }
        }
        $key = strtr($key, [
            'ç' => 'c', 'Ç' => 'C',
            'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'I' => 'I',
            'İ' => 'I', 'ö' => 'o',
            'Ö' => 'O', 'ş' => 's',
            'Ş' => 'S', 'ü' => 'u',
            'Ü' => 'U',
        ]);

        return preg_replace('/[^a-z0-9.]+/', '', strtolower($key)) ?? '';
    };

    $resolveImagePath = static function (string $basePath, string $safeName) use ($imageKey): ?string {
        $path = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($path) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) {
            return $path;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            return null;
        }

        $needle = $imageKey(basename($safeName));
        foreach (scandir($directory) ?: [] as $entry) {
            $entryPath = $directory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($entryPath) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $entry)) {
                continue;
            }

            if ($imageKey($entry) === $needle) {
                return $entryPath;
            }
        }

        return null;
    };

    foreach ([public_path('Resimler'), base_path('../zemuretim/Resimler')] as $basePath) {
        $path = $resolveImagePath($basePath, $safeName);
        if ($path) {
            return response()->file($path);
        }
    }

    abort(404);
})->where('filename', '.*')->name('legacy.images');

// ===== Stok API =====
Route::prefix('api/stocks')->middleware('auth')->group(function () {
    Route::middleware('permission:view stocks')->group(function () {
        Route::get('/export', [StocksController::class, 'exportWorkbook']);
        Route::get('/movements', [StocksController::class, 'getMovementFeed']);
        Route::get('/', [StocksController::class, 'getStocks']);
        Route::get('/lookups', [StocksController::class, 'getLookups']);
        Route::get('/{id}/movements/export', [StocksController::class, 'exportMovements'])->whereNumber('id');
        Route::get('/{id}/movements', [StocksController::class, 'getMovements'])->whereNumber('id');
    });

    Route::middleware('permission:manage stocks')->group(function () {
        Route::post('/', [StocksController::class, 'store']);
        Route::put('/{id}', [StocksController::class, 'update']);
        Route::delete('/{id}', [StocksController::class, 'destroy']);
        Route::post('/reset-buffer', [StocksController::class, 'resetBuffer']);
        Route::post('/import', [StocksController::class, 'importWorkbook']);
        Route::post('/import-csv', [StocksController::class, 'importWorkbook']);
    });
});

// ===== Reports API =====
Route::prefix('api/reports')->middleware('auth')->group(function () {
    Route::get('/dashboard-stats', [ReportsController::class, 'dashboardStats']);
    Route::get('/lookups', [ReportsController::class, 'lookups']);
    Route::post('/chart-data', [ReportsController::class, 'chartData']);
    Route::get('/tasks', [ReportsController::class, 'getTaskReport']);
    Route::get('/tasks-export', [ReportsController::class, 'exportExcelTasks']);
    Route::get('/personnel-tasks', [ReportsController::class, 'getPersonnelTaskReport']);
    Route::get('/personnel-tasks-export', [ReportsController::class, 'exportPersonnelTasks']);
    Route::get('/performance', [ReportsController::class, 'getPerformanceReport']);

    Route::middleware('permission:view stocks')->group(function () {
        Route::get('/stocks/export', [ReportExportController::class, 'exportStocks']);
    });
    Route::middleware('permission:view planning')->group(function () {
        Route::get('/production/export', [ReportExportController::class, 'exportProduction']);
    });
});

// ===== Database Admin API =====
Route::prefix('api/database')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/personnel', [AdminPersonnelController::class, 'getPersonnel']);
    Route::get('/personnel/idle', [AdminPersonnelController::class, 'getIdlePersonnel']);
    Route::get('/personnel/production-overview', [AdminPersonnelController::class, 'getPersonnelProductionOverview']);
    Route::post('/personnel', [AdminPersonnelController::class, 'storePersonnel']);
    Route::put('/personnel/{id}', [AdminPersonnelController::class, 'updatePersonnel']);
    Route::delete('/personnel/{id}', [AdminPersonnelController::class, 'deletePersonnel']);
    Route::get('/personnel-workload', [AdminPersonnelController::class, 'getPersonnelWorkload']);
    Route::get('/pool-tasks', [AdminPoolTaskController::class, 'getPoolTasks']);
    Route::post('/pool-tasks/{id}/assign', [AdminPoolTaskController::class, 'assignPoolTask']);
    Route::post('/pool-tasks', [AdminPoolTaskController::class, 'storePoolTask']);
    Route::put('/pool-tasks/{id}', [AdminPoolTaskController::class, 'updatePoolTask']);
    Route::delete('/pool-tasks/{id}', [AdminPoolTaskController::class, 'deletePoolTask']);
    Route::post('/pool-tasks/delete-by-product', [AdminPoolTaskController::class, 'deletePoolTasksByProduct']);
    Route::post('/pool-tasks/delete-all', [AdminPoolTaskController::class, 'deleteAllPoolTasks']);

    Route::get('/departments', [AdminDepartmentController::class, 'getDepartments']);
    Route::post('/departments', [AdminDepartmentController::class, 'storeDepartment']);
    Route::put('/departments/{id}', [AdminDepartmentController::class, 'updateDepartment']);
    Route::delete('/departments/{id}', [AdminDepartmentController::class, 'deleteDepartment']);

    Route::get('/components', [AdminComponentController::class, 'getComponents']);
    Route::post('/components', [AdminComponentController::class, 'storeComponent']);
    Route::put('/components/{id}', [AdminComponentController::class, 'updateComponent']);
    Route::delete('/components/{id}', [AdminComponentController::class, 'deleteComponent']);
    Route::put('/components/{id}/bom-path', [AdminComponentController::class, 'updateComponentBomPath']);
    Route::delete('/components/{id}/bom-path', [AdminComponentController::class, 'clearComponentBomPath']);

    Route::get('/products', [AdminProductController::class, 'getProducts']);
    Route::post('/products', [AdminProductController::class, 'storeProduct']);
    Route::post('/products/merge-preview', [AdminProductController::class, 'previewProductMerge']);
    Route::post('/products/merge', [AdminProductController::class, 'mergeProducts']);
    Route::put('/products/{id}', [AdminProductController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminProductController::class, 'deleteProduct']);
    Route::post('/products/{id}/image', [AdminProductController::class, 'uploadProductImage']);
    Route::post('/{module}/import', [AdminImportController::class, 'importModule'])
        ->where('module', 'personnel|departments|components|products');

    Route::get('/components/{id}/bom-path-names', [AdminComponentController::class, 'getComponentBomPathNames']);
    Route::get('/products/{id}/bom-path-names', [AdminProductController::class, 'getProductBomPathNames']);
    Route::post('/derive-product', [AdminProductController::class, 'deriveProduct']);

    Route::get('/product-settings/lookups', [AdminSettingsController::class, 'getProductSettingsLookups']);
    Route::get('/product-settings/{urunNo}', [AdminSettingsController::class, 'getProductSettingsDetails']);
    Route::post('/product-settings/update', [AdminSettingsController::class, 'updateProductSettings']);
});

// ===== Personnel Panel API =====
Route::prefix('api/panel')->middleware('auth')->group(function () {
    Route::get('/dashboard-stats', [PanelTaskController::class, 'dashboardStats']);
    Route::get('/my-tasks', [PanelTaskController::class, 'myTasks']);
    Route::get('/task/{id}', [PanelTaskController::class, 'taskDetail']);
    Route::get('/task/{id}/dependency-info', [PanelDependencyController::class, 'dependencyInfo']);
    Route::post('/task/{id}/notify-dependency', [PanelDependencyController::class, 'notifyDependency']);
    Route::post('/task/{id}/start', [PanelTaskController::class, 'startTask']);
    Route::post('/task-group/start', [PanelTaskController::class, 'startTaskGroup']);
    Route::post('/task/{id}/complete', [PanelTaskController::class, 'completeProduction']);
    Route::get('/available-tasks', [PanelAvailableTaskController::class, 'availableTasks']);
    Route::post('/take-task/{id}', [PanelAvailableTaskController::class, 'takeTask']);
    Route::get('/completed-tasks', [PanelReportController::class, 'completedTasks']);
    Route::get('/task-report', [PanelReportController::class, 'taskReport']);
    Route::get('/assigned-to-me', [PanelReportController::class, 'assignedToMe']);
    Route::get('/messages/unread-count', [PanelMessageController::class, 'unreadMessageCount']);
    Route::post('/messages/mark-read', [PanelMessageController::class, 'markMessagesRead']);
    Route::get('/messages', [PanelMessageController::class, 'messages']);
    Route::post('/messages', [PanelMessageController::class, 'sendMessage']);
    Route::delete('/messages/{id}', [PanelMessageController::class, 'deleteMessage']);
    Route::delete('/task/{id}', [PanelTaskController::class, 'deleteTask']);
});

// ===== Üretim Planlama API =====
Route::prefix('api/planning')->middleware(['auth', 'admin'])->group(function () {
    Route::middleware('permission:view planning')->group(function () {
        Route::get('/personnel', [ProductionPlanningController::class, 'getPersonnelList']);
        Route::get('/personnel/{personelNo}/tasks', [ProductionPlanningController::class, 'getPersonnelTasks']);
        Route::get('/task/{id}/dependency-info', [ProductionPlanningController::class, 'dependencyInfo']);
        Route::get('/task/{id}/transfer-options', [ProductionPlanningController::class, 'getTransferOptions']);
        Route::get('/department/{id}/pool', [ProductionPlanningController::class, 'getPoolTasks']);
        Route::get('/department/{id}/tasks', [ProductionPlanningController::class, 'getDepartmentPersonnelTasks']);
        Route::get('/task/{id}/history', [ProductionPlanningController::class, 'getTaskHistory']);
    });

    Route::middleware('permission:manage planning')->group(function () {
        Route::post('/increment/{id}', [ProductionPlanningController::class, 'incrementTask']);
        Route::post('/decrement/{id}', [ProductionPlanningController::class, 'decrementTask']);
        Route::post('/task/{id}/notify-dependency', [ProductionPlanningController::class, 'notifyDependency']);
        Route::delete('/task/{id}', [ProductionPlanningController::class, 'deleteTask']);
        Route::put('/task/{id}/date', [ProductionPlanningController::class, 'updateTaskDate']);
        Route::post('/task/{id}/transfer', [ProductionPlanningController::class, 'transferTask']);
        Route::post('/pool/assign', [ProductionPlanningController::class, 'assignFromPool']);
        Route::put('/task/{id}/quantity', [ProductionPlanningController::class, 'setTaskQuantity']);
    });
});

// ===== AI Forecasting API =====
Route::prefix('api/forecast')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/products', [ForecastController::class, 'index']);
    Route::get('/product/{id}', [ForecastController::class, 'detail']);
    Route::post('/approve/{id}', [ForecastController::class, 'approve']);
    Route::post('/override/{id}', [ForecastController::class, 'override']);
    Route::post('/run-now', [ForecastController::class, 'runForecast']);
});

// ===== Authenticated Personnel Pages =====
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Auth::user()?->isAdmin()
            ? redirect()->route('admin.index')
            : redirect()->route('user.dashboard');
    })->name('dashboard');

    Route::get('/KullaniciAnaSayfa.aspx', function () {
        return redirect()->route('user.tasks');
    })->name('legacy.user.dashboard');
    Route::get('/AlinabilecekGorevler.aspx', function () { return redirect()->route('user.available'); })->name('legacy.user.available');
    Route::get('/Iletisim.aspx', function () { return redirect()->route('user.messages'); })->name('legacy.user.messages');
    Route::get('/TamamlananGorevler.aspx', function () {
        return Auth::user()?->isAdmin()
            ? redirect()->route('reports.completed')
            : redirect()->route('user.completed');
    })->name('legacy.user.completed');

    // Personel Paneli Views
    Route::get('/panel', function () { return redirect()->route('user.tasks'); })->name('user.dashboard');
    Route::get('/panel/gorevlerim', function () { return view('user.tasks'); })->name('user.tasks');
    Route::get('/panel/gorev/{id}', function ($id) { return view('user.task-detail', ['id' => $id]); })->name('user.task-detail');
    Route::get('/panel/alinabilir', function () { return view('user.available-tasks'); })->name('user.available');
    Route::get('/panel/tamamlanan', function () { return view('user.completed-tasks'); })->name('user.completed');
    Route::get('/panel/rapor', function () { return view('user.task-report'); })->name('user.report');
    Route::get('/panel/verilen-gorevler', function () { return redirect()->route('user.tasks'); })->name('user.assigned');
    Route::get('/panel/mesajlar', function () { return view('user.messages'); })->name('user.messages');
    Route::get('/panel/sifre-degistir', function () { return view('user.password'); })->name('user.password');
    Route::post('/panel/sifre-degistir', [AuthController::class, 'changePassword'])->name('user.password.update');
    Route::get('/sifre.aspx', function () { return view('user.password'); })->name('legacy.user.password');
    Route::post('/sifre.aspx', [AuthController::class, 'changePassword']);
});

// ===== Admin Routes =====
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/AdminAnaSayfa.aspx', function () { return redirect()->route('admin.index'); })->name('legacy.admin.dashboard');
    Route::get('/Siparisler.aspx', function () { return redirect()->route('orders.index'); });
    Route::get('/OzelUretimTakip.aspx', function () { return redirect()->route('orders.special'); });
    Route::get('/KritikStokEsik.aspx', function () { return redirect()->route('stocks.critical'); });
    Route::get('/UrunEslestirme.aspx', function () { return redirect()->route('products.match'); });
    Route::get('/UrunOzellikleriAyarlari.aspx', function () { return redirect()->route('products.settings'); });
    Route::get('/Stoklar.aspx', function () { return redirect()->route('stocks.index'); });
    Route::get('/VeritabaniDuzenle.aspx', function () { return redirect()->route('admin.database'); });
    Route::get('/Istatistikler.aspx', function () { return redirect()->route('reports.statistics'); });
    Route::get('/IsEmriVer.aspx', function () { return redirect()->route('workorders.create'); });
    Route::get('/TopluIsEmriVerme.aspx', function () { return redirect()->route('workorders.bulk'); });
    Route::get('/IsEmriGecmisi.aspx', function () { return redirect()->route('workorders.center'); });
    Route::get('/GorevAtama.aspx', function () { return redirect()->route('tasks.assign'); });
    Route::get('/UretimBekleyenOzet.aspx', function () { return redirect()->route('production.pending'); });
    Route::get('/UretimPlanlama.aspx', function () { return redirect()->route('production.planning'); });
    Route::get('/PasifDevamEdenler.aspx', function () { return redirect()->route('production.pasif'); });
    Route::get('/YeniUrunEkle.aspx', function () {
        return redirect()->route('products.create', array_merge(request()->query(), ['tab' => 'add']));
    });
    Route::get('/YeniUrunDuzenle.aspx', function () {
        return redirect()->route('products.create', array_merge(request()->query(), ['tab' => 'derive']));
    });
    Route::get('/PersonelRapor.aspx', function () { return redirect()->route('reports.personnel'); });
    Route::get('/GorevGoruntuleme.aspx', function () { return redirect()->route('reports.personnel'); });
    Route::get('/GorevRapor.aspx', function () { return redirect()->route('reports.tasks'); });
    Route::get('/sifreYonetici.aspx', function () { return redirect()->route('admin.password'); });

    Route::get('/siparisler', [AdminController::class, 'orders'])->name('orders.index');
    Route::get('/ozel-uretim', [AdminController::class, 'special'])->name('orders.special');
    Route::get('/kritik-stok', [AdminController::class, 'criticalStocks'])->name('stocks.critical');
    Route::get('/urun-eslestirme', [AdminController::class, 'productMatch'])->name('products.match');
    Route::get('/urun-ayarlari', [AdminController::class, 'productSettings'])->name('products.settings');
    Route::get('/stoklar', [AdminController::class, 'stocks'])->name('stocks.index');

    Route::get('/veritabani', [AdminController::class, 'database'])->name('admin.database');
    Route::get('/istatistikler', [AdminController::class, 'statistics'])->name('reports.statistics');

    Route::get('/admin/havuz', function () {
        return view('admin.index');
    })->name('admin.index');
    Route::get('/admin/gorev-atama', function () { return view('admin.tasks'); })->name('admin.tasks');
    Route::get('/admin/stoklar', function () { return view('admin.stocks'); })->name('admin.stocks');

    // İş Emri
    Route::get('/is-emri-ver', function () { return view('workorders.create'); })->name('workorders.create');
    Route::get('/toplu-is-emri', function () { return view('workorders.bulk'); })->name('workorders.bulk');
    Route::get('/is-emri-merkezi', [WorkOrderCenterController::class, 'index'])->name('workorders.center');
    Route::get('/is-emri-gecmisi', function () { return redirect()->route('workorders.center'); })->name('workorders.history');

    // Görev
    Route::get('/gorev-atama', function () { return view('tasks.assign'); })->name('tasks.assign');

    // Üretim
    Route::get('/uretim-bekleyen', function () { return view('production.pending'); })->name('production.pending');
    Route::get('/uretim-planlama', function () { return view('production.planning'); })->name('production.planning');
    Route::get('/pasif-devam-eden', function () { return view('production.pasif-devam-eden'); })->name('production.pasif');
    Route::get('/yapay-zeka-tahmin', [ForecastController::class, 'dashboardView'])->name('forecast.dashboard');

    // Ürün
    Route::get('/yeni-urun', function () { return view('products.create'); })->name('products.create');
    Route::get('/urun-agaci', function () { return view('products.tree'); })->name('products.tree');

    // Raporlar
    Route::get('/personel-rapor', function () { return view('reports.personnel'); })->name('reports.personnel');
    Route::get('/tamamlanan-gorevler', function () {
        return view('reports.tasks', [
            'reportTitle' => 'Tamamlanan Görevler',
            'reportTableTitle' => 'Tamamlanan görev tablosu',
        ]);
    })->name('reports.completed');
    Route::get('/gorev-rapor', function () { return view('reports.tasks'); })->name('reports.tasks');
    Route::get('/isci-performans', function () { return view('reports.performance'); })->name('reports.performance');
    Route::get('/devam-eden-gorevler', function () { return view('reports.ongoing'); })->name('reports.ongoing');

    // Ayarlar
    Route::get('/ayarlar', function () { return view('admin.settings'); })->name('admin.settings');
    Route::post('/api/admin/clear-cache', function () {
        Illuminate\Support\Facades\Artisan::call('cache:clear');
        Illuminate\Support\Facades\Artisan::call('view:clear');
        Illuminate\Support\Facades\Artisan::call('route:clear');
        Illuminate\Support\Facades\Artisan::call('config:clear');
        return response()->json(['success' => true, 'message' => 'Tüm önbellek temizlendi.']);
    })->name('admin.clear-cache');
    Route::post('/api/admin/reset-test-data', [AdminController::class, 'resetTestData'])
        ->name('admin.reset-test-data');

    Route::prefix('api/work-order-preview')->group(function () {
        Route::get('/settings', [WorkOrderPreviewController::class, 'settings']);
        Route::post('/settings', [WorkOrderPreviewController::class, 'updateSettings']);
        Route::post('/preview', [WorkOrderPreviewController::class, 'preview']);
    });

    Route::prefix('api/work-order-center')->group(function () {
        Route::get('/feed', [WorkOrderCenterController::class, 'feed']);
        Route::get('/orders', [WorkOrderCenterController::class, 'orders']);
        Route::get('/export', [WorkOrderCenterController::class, 'export']);
        Route::get('/entity/{type}/{id}', [WorkOrderCenterController::class, 'entity'])->whereNumber('id');
        Route::get('/timeline', [WorkOrderCenterController::class, 'timeline']);
        Route::get('/lookups', [WorkOrderCenterController::class, 'lookups']);
    });

    // ===== Telegram Bildirim Ayarlari =====
    Route::prefix('api/admin/telegram')->group(function () {
        Route::get('/settings', [TelegramSettingsController::class, 'getSettings']);
        Route::post('/settings', [TelegramSettingsController::class, 'updateSettings']);
        Route::post('/test', [TelegramSettingsController::class, 'testConnection']);
        Route::get('/logs', [TelegramSettingsController::class, 'getLogs']);
    });

    Route::get('/sifre-degistir', function () { return view('admin.password'); })->name('admin.password');
    Route::post('/sifre-degistir', [AuthController::class, 'changePassword'])->name('admin.password.update');

    // Personel Yönetimi
    Route::get('/gorevsiz-personel', function () { return view('admin.idle-personnel'); })->name('admin.idle');

    // Mesajlar
    Route::get('/mesajlar', function () { return view('admin.messages'); })->name('admin.messages');
    Route::get('/api/admin/messages', [AdminController::class, 'getMessages']);
    Route::delete('/api/admin/messages/{id}', [AdminController::class, 'deleteMessage']);

    // AI Asistan
    Route::get('/ai-asistan', [AIChatController::class, 'index'])->name('admin.ai-chat');
    Route::post('/api/ai/send', [AIChatController::class, 'send']);
    Route::post('/api/ai/clear', [AIChatController::class, 'clear']);
});

// ===== Telegram Webhook (public - auth gerektirmez) =====
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

// Telegram AI polling (test icin - tarayicidan acilabilir)
Route::get('/telegram/ai-poll', function () {
    $exitCode = Artisan::call('telegram:ai-poll');
    return response()->json([
        'success' => $exitCode === 0,
        'output' => Artisan::output(),
    ]);
});

// Telegram webhook kurulumu (tarayicidan ac - bir kez calistir, sonra sil)
Route::get('/telegram/webhook-kur', function () {
    $token = env('TELEGRAM_BOT_TOKEN', '');
    $webhookUrl = config('app.url', 'https://fa.zolm.com.tr') . '/telegram/webhook';

    if ($token === '') {
        return response()->json(['ok' => false, 'error' => 'Bot token tanimli degil']);
    }

    // Webhook'u kur
    $response = \Illuminate\Support\Facades\Http::timeout(15)
        ->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);

    $result = $response->json();

    // Mevcut durumu goster
    $info = \Illuminate\Support\Facades\Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json();

    $html = '<h2>Telegram Webhook Kurulumu</h2>';
    $html .= '<p><b>URL:</b> ' . e($webhookUrl) . '</p>';

    if ($result['ok'] ?? false) {
        $html .= '<p style="color:green"><b>Webhook basariyla kuruldu!</b></p>';
    } else {
        $html .= '<p style="color:red"><b>Hata:</b> ' . e($result['description'] ?? 'Bilinmeyen hata') . '</p>';
    }

    $html .= '<h3>Mevcut Webhook Durumu</h3>';
    $html .= '<pre>';
    $html .= 'URL: ' . e($info['result']['url'] ?? 'Tanimsiz') . "\n";
    $html .= 'Son hata: ' . e($info['result']['last_error_message'] ?? 'Yok') . "\n";
    $html .= 'Bekleyen: ' . ($info['result']['pending_update_count'] ?? 0) . "\n";
    $html .= '</pre>';
    $html .= '<p style="color:red"><b>ONEMLI:</b> Bu sayfayi bir kez actiktan sonra silin veya route\'u kaldirin!</p>';
    $html .= '<p>Test icin Telegram\'da bot\'a mesaj yazin.</p>';

    return response($html)->header('Content-Type', 'text/html');
});

// ===== Public Pages =====
Route::get('/hakkimizda', function () { return view('pages.about'); })->name('pages.about');
Route::get('/iletisim', function () { return view('pages.contact'); })->name('pages.contact');
Route::get('/sifremi-unuttum', function () { return view('auth.forgot-password'); })->name('password.request');
Route::post('/sifremi-unuttum', [AuthController::class, 'forgotPassword'])->name('password.email');
