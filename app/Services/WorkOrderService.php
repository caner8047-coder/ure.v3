<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * İş Emri Servisi — Legacy tablolar kullanır.
 * BomService'i kullanarak iş emirleri oluşturur.
 */
class WorkOrderService
{
    protected BomService $bomService;
    protected LegacyWorkOrderWriter $legacyWriter;

    public function __construct(BomService $bomService, LegacyWorkOrderWriter $legacyWriter)
    {
        $this->bomService = $bomService;
        $this->legacyWriter = $legacyWriter;
    }

    /**
     * Nihai ürün için iş emri oluştur — BOM ağacını traverse eder.
     */
    public function createWorkOrderForProduct(
        int $urunIDNo,
        int $adet,
        string $stokDurum = 'StokDahil',
        string $aciklama = '',
        array $traceContext = []
    ): array
    {
        return DB::transaction(function () use ($urunIDNo, $adet, $stokDurum, $aciklama, $traceContext) {
            $product = DB::table('tbUrunler')->where('No', $urunIDNo)->first();
            if (!$product) {
                return ['success' => false, 'message' => 'Ürün bulunamadı'];
            }

            $tamponDusumleri = [];
            $recursiveStockMode = $stokDurum === 'StokDahil' ? 'StokDahil' : 'StokHaric';
            $rootComponent = $this->resolveRootComponentForProduct($product);
            if (!$rootComponent) {
                return ['success' => false, 'message' => 'Nihai ürünün kök ara ürünü bulunamadı'];
            }

            $rootComponentNo = (int) $rootComponent->No;
            $yol = $this->bomService->tumYolHazirla((string) $rootComponentNo);
            $uretimAdedi = $this->bomService->minAraUrunUretimiDenetle(
                $urunIDNo,
                $yol,
                (string) $rootComponentNo,
                $adet,
                $aciklama,
                'StokHaric',
                $tamponDusumleri,
                $traceContext
            );

            if ($uretimAdedi > 0 && trim($this->bomService->birAdimOncesiUrunAdlari((string) $rootComponentNo)) !== '') {
                $this->bomService->isEmriVerRecursive(
                    (string) $rootComponentNo,
                    $uretimAdedi,
                    $aciklama,
                    $yol,
                    $urunIDNo,
                    $recursiveStockMode,
                    $tamponDusumleri,
                    $traceContext
                );
            }

            $this->bomService->refreshPoolReadinessForTrace($traceContext, $rootComponentNo);

            $gorevNo = $this->legacyWriter->insertLegacyWorkOrder([
                'UrunIDNo' => $urunIDNo,
                'AraUrunAdiNo' => $rootComponentNo,
                'ToplamAdet' => $adet,
                'BolumAdiNo' => (int) ($rootComponent->BolumAdiNo ?? 0),
                'Performans' => $rootComponent->Performans ?? 0,
                'SiparisSatirNo' => $traceContext['siparisSatirNo'] ?? null,
                'SiparisNo' => $traceContext['siparisNo'] ?? null,
            ]);

            return [
                'success' => true,
                'gorevNo' => $gorevNo,
                'tamponDusumleri' => $tamponDusumleri,
                'sistemUrunAdi' => $product->SistemAdi ?: $product->UrunID,
            ];
        });
    }

    /**
     * Ara ürün (bileşen) için doğrudan iş emri oluştur.
     */
    public function createWorkOrderForComponent(int $araUrunNo, int $adet, string $stokDurum = 'StokDahil', array $traceContext = []): array
    {
        return DB::transaction(function () use ($araUrunNo, $adet, $stokDurum, $traceContext) {
            $araUrun = DB::table('tbAraUrun')->where('No', $araUrunNo)->first();
            if (!$araUrun) {
                return ['success' => false, 'message' => 'Ara ürün bulunamadı'];
            }

            $tamponDusumleri = [];
            $recursiveStockMode = $stokDurum === 'StokDahil' ? 'StokDahil' : 'StokHaric';
            $bolumAdiNo = intval($araUrun->BolumAdiNo ?? 0);
            $yol = $this->bomService->tumYolHazirla((string) $araUrunNo);

            $uretimAdedi = $this->bomService->minAraUrunUretimiDenetle(
                0,
                $yol,
                (string) $araUrunNo,
                $adet,
                '',
                $recursiveStockMode,
                $tamponDusumleri,
                $traceContext
            );

            if ($uretimAdedi <= 0) {
                if (!empty($tamponDusumleri)) {
                    return [
                        'success' => true,
                        'gorevNo' => 0,
                        'fullyCoveredByStock' => true,
                        'tamponDusumleri' => $tamponDusumleri,
                        'requestedQuantity' => $adet,
                        'netProductionQuantity' => 0,
                        'sistemUrunAdi' => $araUrun->AraUrunAdi,
                    ];
                }

                return ['success' => false, 'message' => 'Üretilecek net adet bulunamadı'];
            }

            if ($uretimAdedi > 0 && trim($this->bomService->birAdimOncesiUrunAdlari((string) $araUrunNo)) !== '') {
                $this->bomService->isEmriVerRecursive(
                    (string) $araUrunNo,
                    $uretimAdedi,
                    '',
                    $yol,
                    0,
                    $recursiveStockMode,
                    $tamponDusumleri,
                    $traceContext
                );
            }

            $this->bomService->refreshPoolReadinessForTrace($traceContext, $araUrunNo);

            $gorevNo = $this->legacyWriter->insertLegacyWorkOrder([
                'UrunIDNo' => 0,
                'AraUrunAdiNo' => $araUrunNo,
                'ToplamAdet' => $uretimAdedi,
                'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                'Performans' => $araUrun->Performans ?? 0,
                'SiparisSatirNo' => $traceContext['siparisSatirNo'] ?? null,
                'SiparisNo' => $traceContext['siparisNo'] ?? null,
            ]);

            return [
                'success' => true,
                'gorevNo' => $gorevNo,
                'tamponDusumleri' => $tamponDusumleri,
                'requestedQuantity' => $adet,
                'netProductionQuantity' => $uretimAdedi,
                'sistemUrunAdi' => $araUrun->AraUrunAdi,
            ];
        });
    }

    private function resolveRootComponentForProduct(object $product): ?object
    {
        $urunId = trim((string) ($product->UrunID ?? ''));
        if ($urunId !== '') {
            $directMatch = DB::table('tbAraUrun')->where('AraUrunAdi', $urunId)->first();
            if ($directMatch) {
                return $directMatch;
            }
        }

        $araAdlarYol = trim((string) ($product->AraAdlarYol ?? ''));
        if ($araAdlarYol === '') {
            return null;
        }

        $firstSegment = trim(explode(':', $araAdlarYol)[0] ?? '');
        if (preg_match('/^\d+\-\d+/', $firstSegment) === 1) {
            $rootNo = (int) explode('-', $firstSegment)[0];
            if ($rootNo > 0) {
                return DB::table('tbAraUrun')->where('No', $rootNo)->first();
            }
        }

        $steps = preg_split('/[→>]|->|:/', $araAdlarYol);
        foreach ($steps as $step) {
            $candidate = trim((string) $step);
            if ($candidate === '') {
                continue;
            }

            $component = DB::table('tbAraUrun')->where('AraUrunAdi', $candidate)->first();
            if ($component) {
                return $component;
            }
        }

        return null;
    }

    public function createLegacyManualWorkOrder(
        int $selectedNo,
        string $tur,
        int $adet,
        string $stokDurum = 'StokDahil',
        string $aciklama = '',
        array $traceContext = []
    ): array {
        $normalizedType = $this->normalizeManualType($tur);

        if ($normalizedType === 'Nihai') {
            $legacyRoot = DB::table('tbAraUrun')
                ->where('No', $selectedNo)
                ->where(function ($query) {
                    $query->where('UrunCesidi', 'like', 'Nihayi%')
                        ->orWhere('UrunCesidi', 'like', 'Nihai%');
                })
                ->first();

            if ($legacyRoot) {
                return $this->createLegacyManualRootWorkOrder(
                    $selectedNo,
                    trim((string) ($legacyRoot->AraUrunAdi ?? '')),
                    $adet,
                    $stokDurum,
                    $aciklama,
                    true,
                    $traceContext
                );
            }

            return $this->createWorkOrderForProduct($selectedNo, $adet, $stokDurum, $aciklama, $traceContext);
        }

        if ($normalizedType === 'Ham Madde') {
            return $this->createLegacyManualRootWorkOrder(
                $selectedNo,
                'Ham Madde',
                $adet,
                'StokHaric',
                $aciklama,
                false,
                $traceContext
            );
        }

        return $this->createLegacyManualRootWorkOrder(
            $selectedNo,
            'Ara Mamül',
            $adet,
            $stokDurum,
            $aciklama,
            true,
            $traceContext
        );
    }

    private function createLegacyManualRootWorkOrder(
        int $araUrunNo,
        string $legacyUrunId,
        int $adet,
        string $stokDurum,
        string $aciklama,
        bool $withRecursion,
        array $traceContext = []
    ): array {
        return DB::transaction(function () use ($araUrunNo, $legacyUrunId, $adet, $stokDurum, $aciklama, $withRecursion, $traceContext) {
            $araUrun = DB::table('tbAraUrun')->where('No', $araUrunNo)->first();
            if (!$araUrun) {
                return ['success' => false, 'message' => 'Ara ürün bulunamadı'];
            }

            $tamponDusumleri = [];
            $yol = $this->bomService->tumYolHazirla((string) $araUrunNo);
            $recursiveStockMode = $stokDurum === 'StokDahil' ? 'StokDahil' : 'StokHaric';

            $uretimAdedi = $this->bomService->minAraUrunUretimiDenetle(
                $legacyUrunId,
                $yol,
                (string) $araUrunNo,
                $adet,
                $aciklama,
                $recursiveStockMode,
                $tamponDusumleri,
                $traceContext
            );

            if ($uretimAdedi <= 0) {
                if (!empty($tamponDusumleri)) {
                    return [
                        'success' => true,
                        'gorevNo' => 0,
                        'fullyCoveredByStock' => true,
                        'tamponDusumleri' => $tamponDusumleri,
                        'requestedQuantity' => $adet,
                        'netProductionQuantity' => 0,
                        'sistemUrunAdi' => trim((string) ($araUrun->AraUrunAdi ?? '')),
                    ];
                }

                return ['success' => false, 'message' => 'Üretilecek net adet bulunamadı'];
            }

            if (
                $withRecursion
                && $uretimAdedi > 0
                && trim($this->bomService->birAdimOncesiUrunAdlari((string) $araUrunNo)) !== ''
            ) {
                $this->bomService->isEmriVerRecursive(
                    (string) $araUrunNo,
                    $uretimAdedi,
                    $aciklama,
                    $yol,
                    $legacyUrunId,
                    $recursiveStockMode,
                    $tamponDusumleri,
                    $traceContext
                );
            }

            $this->bomService->refreshPoolReadinessForTrace($traceContext, $araUrunNo);

            $legacyProductNo = $this->bomService->resolveLegacyProductNo($legacyUrunId);
            $gorevNo = $this->legacyWriter->insertLegacyWorkOrder([
                'UrunIDNo' => $legacyProductNo,
                'AraUrunAdiNo' => $araUrunNo,
                'ToplamAdet' => $uretimAdedi,
                'BolumAdiNo' => (int) ($araUrun->BolumAdiNo ?? 0),
                'Performans' => $araUrun->Performans ?? 0,
                'SiparisSatirNo' => $traceContext['siparisSatirNo'] ?? null,
                'SiparisNo' => $traceContext['siparisNo'] ?? null,
            ]);

            $this->recordIssuedTask($araUrunNo, $uretimAdedi, $aciklama);

            return [
                'success' => true,
                'gorevNo' => $gorevNo,
                'tamponDusumleri' => $tamponDusumleri,
                'requestedQuantity' => $adet,
                'netProductionQuantity' => $uretimAdedi,
                'sistemUrunAdi' => trim((string) ($araUrun->AraUrunAdi ?? '')),
            ];
        });
    }

    private function normalizeManualType(string $tur): string
    {
        $normalized = trim($tur);

        return match ($normalized) {
            'Ara', 'Ara Mamul', 'Ara Mamül' => 'Ara Mamül',
            'HamMadde', 'Ham Madde' => 'Ham Madde',
            default => 'Nihai',
        };
    }

    private function recordIssuedTask(int $araUrunNo, int $adet, string $aciklama): void
    {
        DB::table('tbVerilenGorevler')->insert([
            'UrunIDNo' => $araUrunNo,
            'GorevTarihi' => now(),
            'ToplamAdet' => $adet,
            'Aciklama' => trim($aciklama) !== '' ? trim($aciklama) : null,
        ]);
    }
}
