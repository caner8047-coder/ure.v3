<?php

declare(strict_types=1);

use App\Services\OrderSyncService;
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

function hit(HttpKernel $kernel, string $method, string $uri, array $payload = []): array
{
    $session = app('session.store');
    $session->start();
    Auth::guard('web')->logout();
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
        'body' => decodeBody($response->getContent()),
    ];
}

function cleanupByTag(string $tag): array
{
    $orderIds = DB::table('tbSiparisSatir')->where('SiparisNo', 'like', $tag . '%')->pluck('No')->all();
    $productIds = DB::table('tbUrunler')->where('UrunID', 'like', $tag . '%')->pluck('No')->all();
    $componentIds = DB::table('tbAraUrun')->where('AraUrunAdi', 'like', $tag . '%')->pluck('No')->all();

    if (!empty($productIds)) {
        DB::table('tbBolumAraStok')->whereIn('UrunIDNo', $productIds)->delete();
        DB::table('tbPersonelGorev')->whereIn('UrunIDNo', $productIds)->delete();
        DB::table('tbBolumHavuz')->whereIn('UrunIDNo', $productIds)->delete();
        DB::table('tbGorevler')->whereIn('UrunIDNo', $productIds)->delete();
    }

    if (!empty($orderIds)) {
        DB::table('tbIsEmriGecmisi')->whereIn('SiparisSatirNo', $orderIds)->delete();
        DB::table('tbSiparisSatir')->whereIn('No', $orderIds)->delete();
    }

    if (!empty($productIds)) {
        DB::table('tbUrunler')->whereIn('No', $productIds)->delete();
    }

    if (!empty($componentIds)) {
        DB::table('tbAraUrun')->whereIn('No', $componentIds)->delete();
    }

    return [
        'orders' => DB::table('tbSiparisSatir')->where('SiparisNo', 'like', $tag . '%')->count(),
        'products' => DB::table('tbUrunler')->where('UrunID', 'like', $tag . '%')->count(),
        'components' => DB::table('tbAraUrun')->where('AraUrunAdi', 'like', $tag . '%')->count(),
    ];
}

$baseTag = 'SMOKE_ORDER_' . now()->format('Ymd_His');
$productNo = null;
$componentNo = null;
$result = ['ok' => false, 'steps' => []];
$uploadOk = false;
$createOk = false;
$cancelOk = false;
$statusOk = false;
$passivateOk = false;

try {
    $componentNo = DB::table('tbAraUrun')->insertGetId([
        'AraUrunAdi' => $baseTag,
        'Performans' => 8,
        'BolumAdiNo' => 1,
        'MinAdet' => 1,
        'UrunCesidi' => 'Nihayi Urun',
        'Yol' => '',
        'SistemAdi' => $baseTag,
        'SistemKodu' => $baseTag,
    ]);

    $productNo = DB::table('tbUrunler')->insertGetId([
        'UrunID' => $baseTag,
        'AraAdlarYol' => null,
        'SistemAdi' => $baseTag,
        'SistemKodu' => $baseTag,
    ]);

    $result['steps']['seed'] = [
        'product_no' => $productNo,
        'component_no' => $componentNo,
        'tag' => $baseTag,
    ];

    DB::beginTransaction();
    try {
        $uploadTag = $baseTag . '_UP';
        $uploadRows = [[
            'siparisNo' => $uploadTag,
            'pazaryeri' => 'Smoke',
            'magaza' => 'Smoke',
            'siparisTarihi' => now()->toDateTimeString(),
            'musteri' => 'Smoke Musteri',
            'urunAdi' => $baseTag,
            'adet' => 2,
            'musteriNotu' => 'upload smoke',
            'kargoSonTeslim' => now()->addDay()->toDateTimeString(),
            'kategori' => 'Smoke',
            'stokKodu' => $baseTag,
        ]];

        $uploadResult = app(OrderSyncService::class)->uploadOrders($uploadRows);
        $uploadedRow = DB::table('tbSiparisSatir')->where('SiparisNo', $uploadTag)->first();

        $result['steps']['upload_dry_run'] = [
            'service' => $uploadResult,
            'row' => $uploadedRow,
        ];

        $uploadOk = ($uploadResult['success'] ?? false)
            && $uploadedRow !== null
            && intval($uploadedRow->SetMi ?? -1) === 0
            && intval($uploadedRow->EslesenUrunNo ?? 0) === $productNo;
    } finally {
        DB::rollBack();
    }

    $orderTag = $baseTag . '_FLOW';
    $orderNo = DB::table('tbSiparisSatir')->insertGetId([
        'SiparisNo' => $orderTag,
        'Pazaryeri' => 'Smoke',
        'Magaza' => 'Smoke',
        'SiparisTarihi' => now(),
        'Musteri' => 'Smoke Musteri',
        'UrunAdi' => $baseTag,
        'Adet' => 2,
        'MusteriNotu' => 'flow smoke',
        'KargoSonTeslim' => now()->addDay(),
        'Kategori' => 'Smoke',
        'Durum' => 'UretimBekliyor',
        'Aktif' => 1,
        'EslesenUrunNo' => $productNo,
        'EslesenUrunTur' => 'Nihai',
        'EslesmePuani' => 100,
        'EslesmeYontemi' => 'Manuel',
        'YuklemeTarihi' => now(),
        'SetMi' => 0,
        'SetNo' => null,
        'AnaSetSatirNo' => null,
    ]);

    $createResponse = hit($kernel, 'POST', '/SiparisApi.ashx?action=createOrderWorkOrders', [
        'satirNolar' => [$orderNo],
        'surplus' => 1,
    ]);

    $orderRows = DB::table('tbSiparisSatir')
        ->where(function ($query) use ($orderTag) {
            $query->where('SiparisNo', $orderTag)
                ->orWhere('SiparisNo', 'like', 'STOK-%');
        })
        ->where('UrunAdi', $baseTag)
        ->orderBy('No')
        ->get();

    $createdOrderIds = $orderRows->pluck('No')->map(fn ($value) => (int) $value)->all();

    $result['steps']['create_order_work_orders'] = [
        'response' => $createResponse,
        'order_rows' => $orderRows,
        'history' => DB::table('tbIsEmriGecmisi')->whereIn('SiparisSatirNo', $createdOrderIds)->orderBy('No')->get(),
        'pool' => DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->orderBy('No')->get(),
        'tasks' => DB::table('tbGorevler')->where('UrunIDNo', $productNo)->orderBy('No')->get(),
    ];

    $createOk = ($createResponse['body']['success'] ?? false)
        && count($createdOrderIds) === 2
        && $orderRows->every(fn ($row) => ($row->Durum ?? '') === 'IsEmriVerildi' && intval($row->GorevNo ?? 0) > 0)
        && DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->value('ToplamAdet') == 3
        && DB::table('tbGorevler')->where('UrunIDNo', $productNo)->count() === 2;

    $cancelResponse = hit($kernel, 'POST', '/SiparisApi.ashx?action=cancelBulkWorkOrders', [
        'satirNolar' => $createdOrderIds,
    ]);

    $result['steps']['cancel_bulk'] = [
        'response' => $cancelResponse,
        'orders' => DB::table('tbSiparisSatir')->whereIn('No', $createdOrderIds)->orderBy('No')->get(),
        'history' => DB::table('tbIsEmriGecmisi')->whereIn('SiparisSatirNo', $createdOrderIds)->orderBy('No')->get(),
        'remaining_pool' => DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->count(),
    ];

    $cancelHistory = collect($result['steps']['cancel_bulk']['history'] ?? []);
    $cancelOk = ($cancelResponse['body']['success'] ?? false)
        && intval($result['steps']['cancel_bulk']['remaining_pool'] ?? -1) === 0
        && collect($result['steps']['cancel_bulk']['orders'] ?? [])->every(
            fn ($row) => ($row->Durum ?? '') === 'UretimBekliyor' && ($row->GorevNo ?? null) === null
        )
        && $cancelHistory->where('IslemTipi', 'IptalEdildi')->count() === count($createdOrderIds);

    $statusOrderNo = DB::table('tbSiparisSatir')->insertGetId([
        'SiparisNo' => $baseTag . '_STATUS',
        'Pazaryeri' => 'Smoke',
        'Magaza' => 'Smoke',
        'SiparisTarihi' => now(),
        'Musteri' => 'Smoke Musteri',
        'UrunAdi' => $baseTag,
        'Adet' => 1,
        'Kategori' => 'Smoke',
        'Durum' => 'UretimBekliyor',
        'Aktif' => 1,
        'EslesenUrunNo' => $productNo,
        'EslesenUrunTur' => 'Nihai',
        'EslesmePuani' => 100,
        'EslesmeYontemi' => 'Manuel',
        'YuklemeTarihi' => now(),
        'SetMi' => 0,
        'SetNo' => null,
        'AnaSetSatirNo' => null,
    ]);

    $statusResponse = hit($kernel, 'POST', '/SiparisApi.ashx?action=onlyUpdateStatusBulk', [
        'satirNolar' => [$statusOrderNo],
    ]);

    $result['steps']['only_update_status_bulk'] = [
        'response' => $statusResponse,
        'order' => DB::table('tbSiparisSatir')->where('No', $statusOrderNo)->first(),
    ];

    $statusOrder = $result['steps']['only_update_status_bulk']['order'];
    $statusOk = ($statusResponse['body']['success'] ?? false)
        && ($statusOrder->Durum ?? '') === 'StokKarsilandi';

    $passiveOrderNo = DB::table('tbSiparisSatir')->insertGetId([
        'SiparisNo' => $baseTag . '_PASSIVE',
        'Pazaryeri' => 'Smoke',
        'Magaza' => 'Smoke',
        'SiparisTarihi' => now(),
        'Musteri' => 'Smoke Musteri',
        'UrunAdi' => $baseTag,
        'Adet' => 1,
        'Kategori' => 'Smoke',
        'Durum' => 'IsEmriVerildi',
        'Aktif' => 1,
        'EslesenUrunNo' => $productNo,
        'EslesenUrunTur' => 'Nihai',
        'EslesmePuani' => 100,
        'EslesmeYontemi' => 'Manuel',
        'GorevNo' => 999999,
        'IsEmriTarihi' => now(),
        'YuklemeTarihi' => now(),
        'SetMi' => 0,
        'SetNo' => null,
        'AnaSetSatirNo' => null,
    ]);

    DB::table('tbBolumHavuz')->insert([
        'UrunIDNo' => $productNo,
        'GorevBaslangicTarihi' => now()->format('d/m/Y'),
        'BolumAdiNo' => 1,
        'Aciklama' => 'passive smoke',
        'GorevBaslangicSaati' => now()->format('H:i'),
        'AraUrunAdiNo' => $componentNo,
        'Adet' => 1,
        'ToplamAdet' => 1,
    ]);

    $passivateResponse = hit($kernel, 'POST', '/SiparisApi.ashx?action=passivateWithWorkOrderCancel', [
        'satirNolar' => [$passiveOrderNo],
    ]);
    $afterPassivate = DB::table('tbSiparisSatir')->where('No', $passiveOrderNo)->first();

    DB::table('tbSiparisSatir')->where('No', $passiveOrderNo)->update([
        'Durum' => 'PasifDevamEden',
        'Aktif' => 0,
        'GuncellemeTarihi' => now(),
    ]);

    $reactivateResponse = hit($kernel, 'POST', '/SiparisApi.ashx?action=reactivateOrder', [
        'satirNo' => $passiveOrderNo,
    ]);

    $result['steps']['passivate_and_reactivate'] = [
        'passivate' => $passivateResponse,
        'after_passivate' => $afterPassivate,
        'reactivate' => $reactivateResponse,
        'after_reactivate' => DB::table('tbSiparisSatir')->where('No', $passiveOrderNo)->first(),
        'remaining_pool' => DB::table('tbBolumHavuz')->where('UrunIDNo', $productNo)->count(),
    ];

    $afterReactivate = $result['steps']['passivate_and_reactivate']['after_reactivate'];
    $passivateOk = ($passivateResponse['body']['success'] ?? false)
        && ($afterPassivate->Durum ?? '') === 'Pasif'
        && intval($afterPassivate->Aktif ?? -1) === 0
        && ($reactivateResponse['body']['success'] ?? false)
        && ($afterReactivate->Durum ?? '') === 'IsEmriVerildi'
        && intval($afterReactivate->Aktif ?? -1) === 1
        && intval($result['steps']['passivate_and_reactivate']['remaining_pool'] ?? -1) === 0;

    $result['ok'] = $uploadOk && $createOk && $cancelOk && $statusOk && $passivateOk;
} catch (Throwable $e) {
    $result['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
} finally {
    $result['cleanup'] = cleanupByTag($baseTag);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($result['ok'] ? 0 : 1);
