<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkOrderAnomalyDetector
{
    public function detectOrderItemAlerts(
        object $order,
        Collection $poolRows,
        Collection $activeTaskRows,
        Collection $completedTaskRows
    ): array {
        $alerts = [];
        $status = (string) ($order->Durum ?? '');
        $orderItemNo = intval($order->No ?? 0);
        $workOrderNo = intval($order->GorevNo ?? 0);
        $specialProductionNo = intval($order->BagliOlduguOzelUretimNo ?? 0);
        $hasProductionArtifacts = $workOrderNo > 0 || $poolRows->isNotEmpty() || $activeTaskRows->isNotEmpty() || $completedTaskRows->isNotEmpty();

        if ($status === 'IsEmriVerildi' && $workOrderNo <= 0) {
            $alerts[] = [
                'code' => 'missing_work_order_no',
                'severity' => 'high',
                'message' => 'Kayit IsEmriVerildi durumunda ama GorevNo bos gorunuyor.',
                'suggested_fix' => 'Is emri yazma akislarini ve ilgili siparis kaydini kontrol edin.',
            ];
        }

        if ($status === 'UretimBekliyor' && $hasProductionArtifacts) {
            $alerts[] = [
                'code' => 'waiting_status_has_production_artifacts',
                'severity' => 'high',
                'message' => 'Kayit UretimBekliyor durumunda ama is emri veya uretim izi gorunuyor.',
                'suggested_fix' => 'Siparis durumu ile GorevNo, havuz ve personel kayitlarini yeniden senkronlayin.',
            ];
        }

        if ($specialProductionNo > 0 && !in_array($status, ['UretimdenKarsilaniyor', 'StokKarsilandi'], true)) {
            $alerts[] = [
                'code' => 'wip_status_mismatch',
                'severity' => 'medium',
                'message' => 'GIED baglantisi var ama durum bu baglanti ile uyumlu degil.',
                'suggested_fix' => 'BagliOlduguOzelUretimNo ve durum gecisini birlikte kontrol edin.',
            ];
        }

        if ($status === 'StokKarsilandi' && $specialProductionNo > 0) {
            $alerts[] = [
                'code' => 'stock_has_wip_link',
                'severity' => 'high',
                'message' => 'Kayit stoktan kapanmis ama GIED baglantisi hala dolu gorunuyor.',
                'suggested_fix' => 'Bagli oldugu ozel uretim rezervasyonunu temizleyin ve stok akisini kontrol edin.',
            ];
        }

        if (in_array($status, ['IsEmriVerildi', 'UretimdenKarsilaniyor', 'PasifDevamEden'], true)
            && $poolRows->isEmpty()
            && $activeTaskRows->isEmpty()
        ) {
            $alerts[] = [
                'code' => 'no_open_production_rows',
                'severity' => 'medium',
                'message' => 'Kayit uretim akisi icinde ama acik havuz veya aktif personel gorevi bulunamadi.',
                'suggested_fix' => 'Ghost order kontrolu veya uretim izleme kayitlarini kontrol edin.',
            ];
        }

        if ($status === 'Pasif' && ($poolRows->isNotEmpty() || $activeTaskRows->isNotEmpty())) {
            $alerts[] = [
                'code' => 'passive_has_open_rows',
                'severity' => 'high',
                'message' => 'Kayit Pasif durumda ama acik havuz veya aktif personel gorevi hala mevcut.',
                'suggested_fix' => 'Pasife alma ve is emri iptal akislarinin uretim satirlarini tamamen kapattigini dogrulayin.',
            ];
        }

        if ($hasProductionArtifacts && $workOrderNo <= 0 && $status !== 'UretimBekliyor') {
            $alerts[] = [
                'code' => 'production_without_work_order',
                'severity' => 'medium',
                'message' => 'Kayitta uretim izi var ama GorevNo bulunamadi.',
                'suggested_fix' => 'Trace kolonlari, eski kayitlar ve is emri iliskisi kontrol edilmeli.',
            ];
        }

        if ($completedTaskRows->isNotEmpty()
            && $poolRows->isEmpty()
            && $activeTaskRows->isEmpty()
            && in_array($status, ['IsEmriVerildi', 'UretimdenKarsilaniyor'], true)
        ) {
            $alerts[] = [
                'code' => 'completed_work_not_closed',
                'severity' => 'medium',
                'message' => 'Tamamlanan uretim kaydi var ama siparis hala acik uretim durumunda gorunuyor.',
                'suggested_fix' => 'Uretim tamamlama sonrasi siparis durum guncellemesini kontrol edin.',
            ];
        }

        if ($status === 'StokKarsilandi' && $this->supportsEvents()) {
            $hasStockEvent = DB::table('work_order_events')
                ->where('order_item_no', $orderItemNo)
                ->where('event_type', 'stock_deducted')
                ->exists();

            if (!$hasStockEvent) {
                $alerts[] = [
                    'code' => 'missing_stock_event',
                    'severity' => 'low',
                    'message' => 'Kayit stoktan karsilanmis gorunuyor ama ilgili event kaydi bulunamadi.',
                    'suggested_fix' => 'Legacy kayit olabilir; backfill veya manuel inceleme yapin.',
                ];
            }
        }

        return $alerts;
    }

    public function detectWorkOrderAlerts(object $workOrder, ?string $departmentName = null): array
    {
        $alerts = [];

        if (intval($workOrder->SiparisSatirNo ?? 0) <= 0) {
            $alerts[] = [
                'code' => 'orphan_work_order',
                'severity' => 'high',
                'message' => 'Is emri kaydinin bagli siparis satiri bulunamadi.',
                'suggested_fix' => 'Is emri olusturma akisi ve legacy kayit iliskileri kontrol edilmeli.',
            ];
        }

        if (trim((string) $departmentName) === '') {
            $alerts[] = [
                'code' => 'missing_department',
                'severity' => 'medium',
                'message' => 'Is emri uzerinde bolum bilgisi eksik gorunuyor.',
                'suggested_fix' => 'BolumAdiNo ve departman iliskisini dogrulayin.',
            ];
        }

        if (intval($workOrder->ToplamAdet ?? 0) <= 0) {
            $alerts[] = [
                'code' => 'non_positive_work_order_quantity',
                'severity' => 'medium',
                'message' => 'Is emri toplam adedi sifir veya negatif gorunuyor.',
                'suggested_fix' => 'Is emri adet hesaplarini ve BOM yazimini kontrol edin.',
            ];
        }

        if ($this->supportsEvents()) {
            $workOrderNo = intval($workOrder->No ?? 0);
            $hasTimeline = DB::table('work_order_events')
                ->where(function ($query) use ($workOrderNo) {
                    $query->where('work_order_no', $workOrderNo)
                        ->orWhere(function ($subQuery) use ($workOrderNo) {
                            $subQuery->where('aggregate_type', 'work_order')
                                ->where('aggregate_id', $workOrderNo);
                        });
                })
                ->exists();

            if (!$hasTimeline) {
                $alerts[] = [
                    'code' => 'work_order_without_events',
                    'severity' => 'low',
                    'message' => 'Bu is emri icin merkezde olay kaydi bulunamadi.',
                    'suggested_fix' => 'Legacy bir kayit olabilir; backfill veya event logging kapsami kontrol edilmeli.',
                ];
            }
        }

        return $alerts;
    }

    private function supportsEvents(): bool
    {
        return Schema::hasTable('work_order_events');
    }
}
