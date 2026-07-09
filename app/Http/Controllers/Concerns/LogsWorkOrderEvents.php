<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LogsWorkOrderEvents
{
    private function buildTaskEventBase(object $task): array
    {
        $orderItemNo = intval($task->SiparisSatirNo ?? 0);
        $orderNo = trim((string) ($task->SiparisNo ?? ''));
        $workOrderNo = null;

        if ($orderItemNo > 0 && Schema::hasTable('tbSiparisSatir')) {
            $orderRow = DB::table('tbSiparisSatir')
                ->where('No', $orderItemNo)
                ->select('SiparisNo', 'GorevNo')
                ->first();

            if ($orderRow) {
                if ($orderNo === '') {
                    $orderNo = trim((string) ($orderRow->SiparisNo ?? ''));
                }

                $resolvedWorkOrderNo = intval($orderRow->GorevNo ?? 0);
                $workOrderNo = $resolvedWorkOrderNo > 0 ? $resolvedWorkOrderNo : null;
            }
        }

        $taskNo = intval($task->No ?? 0);

        return [
            'aggregate_type' => $orderItemNo > 0 ? 'order_item' : 'personnel_task',
            'aggregate_id' => $orderItemNo > 0 ? $orderItemNo : max(1, $taskNo),
            'order_item_no' => $orderItemNo > 0 ? $orderItemNo : null,
            'order_no' => $orderNo !== '' ? $orderNo : null,
            'work_order_no' => $workOrderNo,
            'personnel_task_no' => $taskNo > 0 ? $taskNo : null,
        ];
    }

    private function logTaskEvent(string $eventType, object $task, ?object $afterTask = null, array $attributes = []): void
    {
        app(\App\Services\WorkOrderEventLogger::class)->log(array_merge(
            $this->buildTaskEventBase($task),
            [
                'event_type' => $eventType,
                'payload_before' => $this->serializeRecord($task),
                'payload_after' => $this->serializeRecord($afterTask),
            ],
            $attributes
        ));
    }

    private function logCenterEvent(array $attributes): void
    {
        try {
            app(\App\Services\WorkOrderEventLogger::class)->log($attributes);
        } catch (\Throwable) {
            // Event merkezi akisi bozmasin.
        }
    }

    private function logIsEmriGecmisi(int $satirNo, int $gorevNo, int $eslesenUrunNo, string $eslesenUrunTur, string $islemTipi): void
    {
        try {
            if (Schema::hasTable('is_emri_gecmisi')) {
                DB::table('is_emri_gecmisi')->insert([
                    'SiparisSatirNo' => $satirNo,
                    'GorevNo' => $gorevNo,
                    'EslesenUrunNo' => $eslesenUrunNo,
                    'EslesenUrunTur' => $eslesenUrunTur,
                    'IslemTipi' => $islemTipi,
                    'Tarih' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable) {
            // Log hatasi akisi bozmasin.
        }
    }
}
