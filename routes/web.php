<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SiparisApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StocksController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\AdminDatabaseController;
use App\Http\Controllers\TopluIsEmriApiController;
use App\Http\Controllers\PersonnelPanelController;

Route::get('/', function () {
    return redirect()->route('admin.index');
});

// ===== API Endpoints (Backward-compatible) =====
Route::any('/SiparisApi.ashx', [SiparisApiController::class, 'handleEndpoint'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::any('/TopluIsEmriApi.ashx', [TopluIsEmriApiController::class, 'handleEndpoint'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// ===== Auth Routes =====
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ===== Stok API =====
Route::prefix('api/stocks')->middleware('auth')->group(function () {
    Route::get('/', [StocksController::class, 'getStocks']);
    Route::get('/lookups', [StocksController::class, 'getLookups']);
    Route::post('/', [StocksController::class, 'store']);
    Route::put('/{id}', [StocksController::class, 'update']);
    Route::delete('/{id}', [StocksController::class, 'destroy']);
    Route::post('/reset-buffer', [StocksController::class, 'resetBuffer']);
});

// ===== Reports API =====
Route::prefix('api/reports')->middleware('auth')->group(function () {
    Route::get('/dashboard-stats', [ReportsController::class, 'dashboardStats']);
    Route::get('/lookups', [ReportsController::class, 'lookups']);
    Route::post('/chart-data', [ReportsController::class, 'chartData']);
});

// ===== Database Admin API =====
Route::prefix('api/database')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/personnel', [AdminDatabaseController::class, 'getPersonnel']);
    Route::post('/personnel', [AdminDatabaseController::class, 'storePersonnel']);
    Route::put('/personnel/{id}', [AdminDatabaseController::class, 'updatePersonnel']);
    Route::delete('/personnel/{id}', [AdminDatabaseController::class, 'deletePersonnel']);

    Route::get('/departments', [AdminDatabaseController::class, 'getDepartments']);
    Route::post('/departments', [AdminDatabaseController::class, 'storeDepartment']);
    Route::put('/departments/{id}', [AdminDatabaseController::class, 'updateDepartment']);
    Route::delete('/departments/{id}', [AdminDatabaseController::class, 'deleteDepartment']);

    Route::get('/components', [AdminDatabaseController::class, 'getComponents']);
    Route::post('/components', [AdminDatabaseController::class, 'storeComponent']);
    Route::put('/components/{id}', [AdminDatabaseController::class, 'updateComponent']);
    Route::delete('/components/{id}', [AdminDatabaseController::class, 'deleteComponent']);

    Route::get('/products', [AdminDatabaseController::class, 'getProducts']);
    Route::post('/products', [AdminDatabaseController::class, 'storeProduct']);
    Route::put('/products/{id}', [AdminDatabaseController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminDatabaseController::class, 'deleteProduct']);

    Route::get('/product-settings/lookups', [AdminDatabaseController::class, 'getProductSettingsLookups']);
    Route::get('/product-settings/{urunNo}', [AdminDatabaseController::class, 'getProductSettingsDetails']);
    Route::post('/product-settings/update', [AdminDatabaseController::class, 'updateProductSettings']);
});

// ===== Personnel Panel API =====
Route::prefix('api/panel')->middleware('auth')->group(function () {
    Route::get('/dashboard-stats', [PersonnelPanelController::class, 'dashboardStats']);
    Route::get('/my-tasks', [PersonnelPanelController::class, 'myTasks']);
    Route::get('/task/{id}', [PersonnelPanelController::class, 'taskDetail']);
    Route::post('/task/{id}/complete', [PersonnelPanelController::class, 'completeProduction']);
    Route::get('/available-tasks', [PersonnelPanelController::class, 'availableTasks']);
    Route::post('/take-task/{id}', [PersonnelPanelController::class, 'takeTask']);
    Route::get('/completed-tasks', [PersonnelPanelController::class, 'completedTasks']);
    Route::get('/task-report', [PersonnelPanelController::class, 'taskReport']);
    Route::get('/assigned-to-me', [PersonnelPanelController::class, 'assignedToMe']);
    Route::get('/messages', [PersonnelPanelController::class, 'messages']);
    Route::post('/messages', [PersonnelPanelController::class, 'sendMessage']);
});

// ===== Authenticated (Non-Admin) Pages =====
Route::middleware('auth')->group(function () {
    Route::get('/siparisler', [AdminController::class, 'orders'])->name('orders.index');
    Route::get('/ozel-uretim', [AdminController::class, 'special'])->name('orders.special');
    Route::get('/kritik-stok', [AdminController::class, 'criticalStocks'])->name('stocks.critical');
    Route::get('/urun-eslestirme', [AdminController::class, 'productMatch'])->name('products.match');
    Route::get('/urun-ayarlari', [AdminController::class, 'productSettings'])->name('products.settings');
    Route::get('/stoklar', [AdminController::class, 'stocks'])->name('stocks.index');
    Route::get('/dashboard', function () { return view('dashboard'); })->name('dashboard');

    // Personel Paneli Views
    Route::get('/panel', function () { return view('user.dashboard'); })->name('user.dashboard');
    Route::get('/panel/gorevlerim', function () { return view('user.tasks'); })->name('user.tasks');
    Route::get('/panel/gorev/{id}', function ($id) { return view('user.task-detail', ['id' => $id]); })->name('user.task-detail');
    Route::get('/panel/alinabilir', function () { return view('user.available-tasks'); })->name('user.available');
    Route::get('/panel/tamamlanan', function () { return view('user.completed-tasks'); })->name('user.completed');
    Route::get('/panel/rapor', function () { return view('user.task-report'); })->name('user.report');
    Route::get('/panel/verilen-gorevler', function () { return view('user.assigned-tasks'); })->name('user.assigned');
    Route::get('/panel/mesajlar', function () { return view('user.messages'); })->name('user.messages');
});

// ===== Admin Routes =====
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/veritabani', [AdminController::class, 'database'])->name('admin.database');
    Route::get('/istatistikler', [AdminController::class, 'statistics'])->name('reports.statistics');

    Route::get('/admin/havuz', function () {
        $havuz = \Illuminate\Support\Facades\DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->select('bh.*', \Illuminate\Support\Facades\DB::raw("IFNULL(a.AraUrunAdi,'') as component_name"), \Illuminate\Support\Facades\DB::raw("IFNULL(b.BolumAdi,'') as department_name"))
            ->orderByDesc('bh.GorevBaslangicTarihi')
            ->paginate(20);
        return view('admin.index', compact('havuz'));
    })->name('admin.index');
    Route::get('/admin/gorev-atama', function () { return view('admin.tasks'); })->name('admin.tasks');
    Route::get('/admin/stoklar', function () { return view('admin.stocks'); })->name('admin.stocks');

    // İş Emri
    Route::get('/is-emri-ver', function () { return view('workorders.create'); })->name('workorders.create');
    Route::get('/toplu-is-emri', function () { return view('workorders.bulk'); })->name('workorders.bulk');
    Route::get('/is-emri-gecmisi', function () { return view('workorders.history'); })->name('workorders.history');

    // Görev
    Route::get('/gorev-atama', function () { return view('tasks.assign'); })->name('tasks.assign');

    // Üretim
    Route::get('/uretim-bekleyen', function () { return view('production.pending'); })->name('production.pending');
    Route::get('/uretim-planlama', function () { return view('production.planning'); })->name('production.planning');

    // Ürün
    Route::get('/yeni-urun', function () { return view('products.create'); })->name('products.create');
    Route::get('/urun-agaci', function () { return view('products.tree'); })->name('products.tree');

    // Raporlar
    Route::get('/personel-rapor', function () { return view('reports.personnel'); })->name('reports.personnel');

    // Ayarlar
    Route::get('/ayarlar', function () { return view('admin.settings'); })->name('admin.settings');
    Route::get('/sifre-degistir', function () { return view('admin.password'); })->name('admin.password');
    Route::post('/sifre-degistir', [AuthController::class, 'changePassword'])->name('admin.password.update');

    // Personel Yönetimi
    Route::get('/gorevsiz-personel', function () { return view('admin.idle-personnel'); })->name('admin.idle');
});

// ===== Public Pages =====
Route::get('/hakkimizda', function () { return view('pages.about'); })->name('pages.about');
Route::get('/iletisim', function () { return view('pages.contact'); })->name('pages.contact');
Route::get('/sifremi-unuttum', function () { return view('auth.forgot-password'); })->name('password.request');
Route::post('/sifremi-unuttum', [AuthController::class, 'forgotPassword'])->name('password.email');
