<?php

declare(strict_types=1);

use App\Models\Personnel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);

function decodeBody(string $body): mixed
{
    $decoded = json_decode($body, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
}

function hit(HttpKernel $kernel, ?Personnel $user, string $method, string $uri, array $payload = []): array
{
    $session = app('session.store');
    $session->start();

    Auth::guard('web')->logout();
    if ($user) {
        Auth::guard('web')->login($user);
    }

    $session->save();

    $cookies = [$session->getName() => $session->getId()];
    $server = [
        'HTTP_HOST' => 'localhost',
        'HTTP_ACCEPT' => 'application/json',
    ];
    $params = $payload;
    $content = null;

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $server['CONTENT_TYPE'] = 'application/json';
        $server['HTTP_X_CSRF_TOKEN'] = $session->token();
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $params = [];
    }

    $request = Request::create($uri, $method, $params, $cookies, [], $server, $content);
    $request->setLaravelSession($session);

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'body' => decodeBody($response->getContent()),
    ];
}

function createTempProduct(string $tag, int $departmentNo, int $performance = 5): array
{
    $componentNo = DB::table('tbAraUrun')->insertGetId([
        'AraUrunAdi' => $tag,
        'Performans' => $performance,
        'BolumAdiNo' => $departmentNo,
        'MinAdet' => 1,
        'UrunCesidi' => 'Nihayi Urun',
        'Yol' => '',
        'SistemAdi' => $tag,
        'SistemKodu' => $tag,
    ]);

    $productNo = DB::table('tbUrunler')->insertGetId([
        'UrunID' => $tag,
        'AraAdlarYol' => null,
        'SistemAdi' => $tag,
        'SistemKodu' => $tag,
    ]);

    return [
        'tag' => $tag,
        'product_no' => $productNo,
        'component_no' => $componentNo,
        'department_no' => $departmentNo,
    ];
}

function createTempComponent(
    string $tag,
    int $departmentNo,
    string $urunCesidi = 'Ara Mamül',
    string $yol = '',
    int $performance = 5
): array {
    $componentNo = DB::table('tbAraUrun')->insertGetId([
        'AraUrunAdi' => $tag,
        'Performans' => $performance,
        'BolumAdiNo' => $departmentNo,
        'MinAdet' => 1,
        'UrunCesidi' => $urunCesidi,
        'Yol' => $yol,
        'SistemAdi' => $tag,
        'SistemKodu' => $tag,
    ]);

    return [
        'tag' => $tag,
        'product_no' => 0,
        'component_no' => $componentNo,
        'department_no' => $departmentNo,
    ];
}

function cleanupTempProduct(array $temp): array
{
    $productNo = (int) ($temp['product_no'] ?? 0);
    $componentNo = (int) ($temp['component_no'] ?? 0);
    $tag = (string) ($temp['tag'] ?? '');

    if ($productNo > 0) {
        DB::table('tbBolumAraStok')->where('UrunIDNo', $productNo)->delete();
        DB::table('tbPersonelGorev')->where('UrunIDNo', $productNo)->delete();
        DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->delete();
        DB::table('tbGorevler')->where('UrunIDNo', $productNo)->delete();
        DB::table('tbIsEmriGecmisi')
            ->where('SistemUrunAdi', $tag)
            ->orWhere('UrunAdi', $tag)
            ->delete();
        DB::table('tbUrunler')->where('No', $productNo)->delete();
    }

    if ($componentNo > 0) {
        DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $componentNo)->delete();
        DB::table('tbPersonelGorev')->where('AraUrunAdiNo', $componentNo)->delete();
        DB::table('tbBolumHavuz')->where('AraUrunAdiNo', $componentNo)->delete();
        DB::table('tbGorevler')->where('AraUrunAdiNo', $componentNo)->delete();
        DB::table('tbVerilenGorevler')->where('UrunIDNo', $componentNo)->delete();
        DB::table('tbAraUrun')->where('No', $componentNo)->delete();
    }

    return [
        'remaining_gorevler' => ($productNo > 0 ? DB::table('tbGorevler')->where('UrunIDNo', $productNo)->count() : 0)
            + ($componentNo > 0 ? DB::table('tbGorevler')->where('AraUrunAdiNo', $componentNo)->count() : 0),
        'remaining_havuz' => ($productNo > 0 ? DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->count() : 0)
            + ($componentNo > 0 ? DB::table('tbBolumHavuz')->where('AraUrunAdiNo', $componentNo)->count() : 0),
        'remaining_personel_gorev' => ($productNo > 0 ? DB::table('tbPersonelGorev')->where('UrunIDNo', $productNo)->count() : 0)
            + ($componentNo > 0 ? DB::table('tbPersonelGorev')->where('AraUrunAdiNo', $componentNo)->count() : 0),
        'remaining_stok' => ($productNo > 0 ? DB::table('tbBolumAraStok')->where('UrunIDNo', $productNo)->count() : 0)
            + ($componentNo > 0 ? DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $componentNo)->count() : 0),
        'remaining_verilen_gorev' => $componentNo > 0 ? DB::table('tbVerilenGorevler')->where('UrunIDNo', $componentNo)->count() : 0,
        'remaining_urun' => $productNo > 0 ? DB::table('tbUrunler')->where('No', $productNo)->count() : 0,
        'remaining_ara_urun' => $componentNo > 0 ? DB::table('tbAraUrun')->where('No', $componentNo)->count() : 0,
    ];
}

function findPoolId(int $productNo): ?int
{
    $poolId = DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->value('No');

    return $poolId !== null ? (int) $poolId : null;
}

function findTaskId(int $productNo): ?int
{
    $taskId = DB::table('tbPersonelGorev')->where('UrunIDNo', $productNo)->value('No');

    return $taskId !== null ? (int) $taskId : null;
}

function findLatestPoolByComponent(int $componentNo): ?object
{
    return DB::table('tbBolumHavuz')
        ->where('AraUrunAdiNo', $componentNo)
        ->orderByDesc('No')
        ->first();
}

function findLatestTaskByComponent(int $componentNo): ?object
{
    return DB::table('tbGorevler')
        ->where('AraUrunAdiNo', $componentNo)
        ->orderByDesc('No')
        ->first();
}

function pageSmoke(HttpKernel $kernel, Personnel $admin, Personnel $worker): array
{
    $adminRoutes = [
        '/admin/havuz',
        '/siparisler',
        '/is-emri-ver',
        '/toplu-is-emri',
        '/is-emri-gecmisi',
        '/gorev-atama',
        '/admin/gorev-atama',
        '/veritabani',
        '/istatistikler',
    ];

    $workerRoutes = [
        '/panel',
        '/panel/gorevlerim',
        '/panel/alinabilir',
        '/panel/tamamlanan',
        '/panel/rapor',
        '/panel/verilen-gorevler',
        '/panel/mesajlar',
    ];

    $result = ['admin' => [], 'worker' => []];

    foreach ($adminRoutes as $route) {
        $response = hit($kernel, $admin, 'GET', $route);
        $result['admin'][$route] = [
            'status' => $response['status'],
            'ok' => $response['status'] === 200,
        ];
    }

    foreach ($workerRoutes as $route) {
        $response = hit($kernel, $worker, 'GET', $route);
        $result['worker'][$route] = [
            'status' => $response['status'],
            'ok' => $response['status'] === 200,
        ];
    }

    return $result;
}

function selfTakeScenario(HttpKernel $kernel, Personnel $worker): array
{
    $temp = [];

    try {
        $temp = createTempProduct('SMOKE_SELF_' . now()->format('Ymd_His'), (int) $worker->BolumAdiNo, 9);
        $manual = hit($kernel, null, 'POST', '/SiparisApi.ashx?action=createManualWorkOrder', [
            'urunNo' => $temp['product_no'],
            'adet' => 2,
            'stokDurum' => 'StokHaric',
        ]);

        $poolId = findPoolId((int) $temp['product_no']);
        $take = hit($kernel, $worker, 'POST', '/api/panel/take-task/' . $poolId, ['adet' => 2]);
        $taskId = findTaskId((int) $temp['product_no']);
        $complete = hit($kernel, $worker, 'POST', '/api/panel/task/' . $taskId . '/complete', ['adet' => 2]);

        return [
            'ok' => ($manual['body']['success'] ?? false)
                && ($take['body']['success'] ?? false)
                && ($complete['body']['success'] ?? false)
                && (int) DB::table('tbBolumHavuz')->where('UrunIDNo', $temp['product_no'])->value('Adet') === 0
                && (int) DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->value('BekleyenAdet') === 0
                && (string) DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->value('Onay') === 'true',
            'manual' => $manual,
            'take' => $take,
            'complete' => $complete,
            'state' => [
                'havuz_adet' => DB::table('tbBolumHavuz')->where('UrunIDNo', $temp['product_no'])->value('Adet'),
                'bekleyen' => DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->value('BekleyenAdet'),
                'onay' => DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->value('Onay'),
                'stok_adet' => DB::table('tbBolumAraStok')->where('UrunIDNo', $temp['product_no'])->value('Adet'),
            ],
            'cleanup' => cleanupTempProduct($temp),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'cleanup' => cleanupTempProduct($temp),
        ];
    }
}

function adminAssignScenario(HttpKernel $kernel, Personnel $admin, Personnel $worker): array
{
    $temp = [];

    try {
        $temp = createTempProduct('SMOKE_ASSIGN_' . now()->format('Ymd_His'), (int) $worker->BolumAdiNo, 6);
        $manual = hit($kernel, null, 'POST', '/SiparisApi.ashx?action=createManualWorkOrder', [
            'urunNo' => $temp['product_no'],
            'adet' => 2,
            'stokDurum' => 'StokHaric',
        ]);

        $poolId = findPoolId((int) $temp['product_no']);
        $assign = hit($kernel, $admin, 'POST', '/api/database/pool-tasks/' . $poolId . '/assign', [
            'personel_no' => (int) $worker->PersonelNo,
            'adet' => 2,
        ]);
        $taskId = findTaskId((int) $temp['product_no']);
        $complete = hit($kernel, $worker, 'POST', '/api/panel/task/' . $taskId . '/complete', ['adet' => 2]);

        return [
            'ok' => ($manual['body']['success'] ?? false)
                && $assign['status'] === 200
                && ($complete['body']['success'] ?? false)
                && (int) DB::table('tbBolumHavuz')->where('UrunIDNo', $temp['product_no'])->value('Adet') === 0,
            'manual' => $manual,
            'assign' => $assign,
            'complete' => $complete,
            'state' => [
                'task_onay' => DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->value('Onay'),
                'havuz_adet' => DB::table('tbBolumHavuz')->where('UrunIDNo', $temp['product_no'])->value('Adet'),
                'stok_adet' => DB::table('tbBolumAraStok')->where('UrunIDNo', $temp['product_no'])->value('Adet'),
            ],
            'cleanup' => cleanupTempProduct($temp),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'cleanup' => cleanupTempProduct($temp),
        ];
    }
}

function authorizationScenario(HttpKernel $kernel, Personnel $sourceWorker, Personnel $otherWorker): array
{
    $temp = [];

    try {
        $temp = createTempProduct('SMOKE_AUTHZ_' . now()->format('Ymd_His'), (int) $sourceWorker->BolumAdiNo, 5);
        hit($kernel, null, 'POST', '/SiparisApi.ashx?action=createManualWorkOrder', [
            'urunNo' => $temp['product_no'],
            'adet' => 1,
            'stokDurum' => 'StokHaric',
        ]);

        $poolId = findPoolId((int) $temp['product_no']);
        $forbidden = hit($kernel, $otherWorker, 'POST', '/api/panel/take-task/' . $poolId, ['adet' => 1]);

        return [
            'ok' => $forbidden['status'] === 422
                && (int) DB::table('tbBolumHavuz')->where('No', $poolId)->value('Adet') === 1
                && DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->count() === 0,
            'forbidden' => $forbidden,
            'state' => [
                'havuz_adet' => DB::table('tbBolumHavuz')->where('No', $poolId)->value('Adet'),
                'created_tasks' => DB::table('tbPersonelGorev')->where('UrunIDNo', $temp['product_no'])->count(),
            ],
            'cleanup' => cleanupTempProduct($temp),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'cleanup' => cleanupTempProduct($temp),
        ];
    }
}

function manualTypeScenario(HttpKernel $kernel, string $type, int $departmentNo): array
{
    $temp = [];

    try {
        $suffix = strtoupper(str_replace([' ', 'Ü', 'ü', 'Ş', 'ş'], ['_', 'U', 'u', 'S', 's'], $type));
        $temp = createTempComponent('SMOKE_' . $suffix . '_' . now()->format('Ymd_His'), $departmentNo, $type, '', 7);
        $manual = hit($kernel, null, 'POST', '/SiparisApi.ashx?action=createManualWorkOrder', [
            'urunNo' => $temp['component_no'],
            'adet' => 2,
            'stokDurum' => $type === 'Ham Madde' ? 'StokHaric' : 'StokDahil',
            'tur' => $type,
            'aciklama' => $type . ' smoke',
        ]);

        $pool = findLatestPoolByComponent((int) $temp['component_no']);
        $task = findLatestTaskByComponent((int) $temp['component_no']);
        $issuedTask = DB::table('tbVerilenGorevler')
            ->where('UrunIDNo', $temp['component_no'])
            ->orderByDesc('No')
            ->first();

        return [
            'ok' => ($manual['body']['success'] ?? false)
                && $pool !== null
                && $task !== null
                && $issuedTask !== null
                && intval($pool->Adet ?? 0) === 2
                && intval($pool->ToplamAdet ?? 0) === 2
                && intval($task->ToplamAdet ?? 0) === 2
                && intval($issuedTask->ToplamAdet ?? 0) === 2
                && intval($pool->BolumAdiNo ?? 0) === $departmentNo
                && intval($task->BolumAdiNo ?? 0) === $departmentNo,
            'manual' => $manual,
            'state' => [
                'pool' => $pool,
                'task' => $task,
                'issued_task' => $issuedTask,
            ],
            'cleanup' => cleanupTempProduct($temp),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'cleanup' => cleanupTempProduct($temp),
        ];
    }
}

$admin = Personnel::where('BolumAdiNo', 0)->firstOrFail();
$worker = Personnel::where('BolumAdiNo', '>', 0)->orderBy('PersonelNo')->firstOrFail();
$workerDeptTwo = Personnel::where('BolumAdiNo', 2)->orderBy('PersonelNo')->first() ?: $worker;
$workerDeptOne = Personnel::where('BolumAdiNo', 1)->orderBy('PersonelNo')->first() ?: $worker;

$result = [
    'generated_at' => now()->toDateTimeString(),
    'dashboard_redirects' => [
        'admin' => hit($kernel, $admin, 'GET', '/dashboard'),
        'worker' => hit($kernel, $worker, 'GET', '/dashboard'),
    ],
    'page_smoke' => pageSmoke($kernel, $admin, $worker),
    'self_take' => selfTakeScenario($kernel, $workerDeptOne),
    'admin_assign' => adminAssignScenario($kernel, $admin, $workerDeptTwo),
    'authorization' => authorizationScenario($kernel, $workerDeptOne, $workerDeptTwo),
    'manual_types' => [
        'ara_mamul' => manualTypeScenario($kernel, 'Ara Mamül', (int) $workerDeptOne->BolumAdiNo),
        'ham_madde' => manualTypeScenario($kernel, 'Ham Madde', (int) $workerDeptOne->BolumAdiNo),
    ],
];

$result['ok'] = ($result['dashboard_redirects']['admin']['status'] ?? 0) === 302
    && ($result['dashboard_redirects']['worker']['status'] ?? 0) === 302
    && $result['self_take']['ok'] === true
    && $result['admin_assign']['ok'] === true
    && $result['authorization']['ok'] === true
    && ($result['manual_types']['ara_mamul']['ok'] ?? false) === true
    && ($result['manual_types']['ham_madde']['ok'] ?? false) === true
    && !in_array(false, array_map(
        fn (array $route) => $route['ok'] ?? false,
        array_merge($result['page_smoke']['admin'], $result['page_smoke']['worker'])
    ), true);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($result['ok'] ? 0 : 1);
