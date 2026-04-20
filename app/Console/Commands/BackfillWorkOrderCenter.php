<?php

namespace App\Console\Commands;

use App\Models\WorkOrderEvent;
use App\Services\WorkOrderEventLogger;
use App\Services\WorkOrderSnapshotProjector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillWorkOrderCenter extends Command
{
    protected $signature = 'work-order-center:backfill
        {--fresh : Mevcut event ve snapshot kayitlarini temizleyip bastan doldur}
        {--skip-current-seed : Gecmisi olmayan mevcut siparis/is emri kayitlarini tohumlama}';

    protected $description = 'Legacy is emri gecmisi ve mevcut durum kayitlarini Is Emri Merkezi tablolarina aktarir.';

    public function handle(
        WorkOrderEventLogger $eventLogger,
        WorkOrderSnapshotProjector $snapshotProjector
    ): int {
        if (!$this->supportsCenterTables()) {
            $this->error('Is Emri Merkezi tabloları bulunamadi. Once migration calistirin.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('work_order_snapshots')->delete();
            DB::table('work_order_events')->delete();
            $this->warn('Mevcut event ve snapshot kayitlari temizlendi.');
        }

        $historyCreated = $this->backfillLegacyHistory($eventLogger);
        $seededOrders = $this->option('skip-current-seed')
            ? 0
            : $this->seedCurrentOrdersWithoutEvents($eventLogger);
        $seededWorkOrders = $this->option('skip-current-seed')
            ? 0
            : $this->seedCurrentWorkOrdersWithoutEvents($eventLogger);
        [$projectedOrders, $projectedWorkOrders] = $this->projectCurrentSnapshots($snapshotProjector);

        $totalEvents = WorkOrderEvent::query()->count();
        $totalSnapshots = DB::table('work_order_snapshots')->count();

        $this->newLine();
        $this->info('Is Emri Merkezi backfill tamamlandi.');
        $this->line("  Legacy gecmisten eklenen event: {$historyCreated}");
        $this->line("  Mevcut durumdan tohumlanan siparis: {$seededOrders}");
        $this->line("  Mevcut durumdan tohumlanan is emri: {$seededWorkOrders}");
        $this->line("  Projeksiyonu guncellenen siparis snapshot: {$projectedOrders}");
        $this->line("  Projeksiyonu guncellenen is emri snapshot: {$projectedWorkOrders}");
        $this->line("  Toplam event: {$totalEvents}");
        $this->line("  Toplam snapshot: {$totalSnapshots}");

        return self::SUCCESS;
    }

    private function backfillLegacyHistory(WorkOrderEventLogger $eventLogger): int
    {
        if (!Schema::hasTable('tbIsEmriGecmisi')) {
            return 0;
        }

        $created = 0;

        DB::table('tbIsEmriGecmisi')
            ->orderBy('No')
            ->chunkById(250, function ($rows) use ($eventLogger, &$created) {
                foreach ($rows as $row) {
                    $eventUuid = $this->deterministicUuid('legacy-history-' . intval($row->No ?? 0));

                    if (WorkOrderEvent::query()->where('event_uuid', $eventUuid)->exists()) {
                        continue;
                    }

                    $mapping = $this->mapLegacyOperation((string) ($row->IslemTipi ?? ''));
                    $aggregate = $this->resolveAggregate(
                        intval($row->SiparisSatirNo ?? 0),
                        intval($row->GorevNo ?? 0)
                    );

                    $eventLogger->log([
                        'event_uuid' => $eventUuid,
                        'event_type' => $mapping['event_type'],
                        'aggregate_type' => $aggregate['aggregate_type'],
                        'aggregate_id' => $aggregate['aggregate_id'],
                        'order_item_no' => $aggregate['order_item_no'],
                        'order_no' => trim((string) ($row->SiparisNo ?? '')) ?: null,
                        'work_order_no' => intval($row->GorevNo ?? 0) > 0 ? intval($row->GorevNo) : null,
                        'status_before' => $mapping['status_before'],
                        'status_after' => $mapping['status_after'],
                        'title_human' => $mapping['title_human'],
                        'summary_human' => $this->buildLegacySummary($row, $mapping['title_human']),
                        'next_step_human' => $mapping['next_step_human'],
                        'source_screen' => 'Legacy Is Emri Gecmisi',
                        'source_action' => trim((string) ($row->IslemTipi ?? '')) ?: 'Legacy Kayit',
                        'source_route' => 'tbIsEmriGecmisi',
                        'actor_type' => 'system',
                        'actor_name' => 'Legacy Kayit',
                        'payload_after' => $this->serializeRecord($row),
                        'context' => [
                            'backfilled' => true,
                            'legacy_history_no' => intval($row->No ?? 0),
                        ],
                        'happened_at' => $row->IslemTarihi ?? now(),
                    ]);

                    $created++;
                }
            }, 'No', 'No');

        return $created;
    }

    private function seedCurrentOrdersWithoutEvents(WorkOrderEventLogger $eventLogger): int
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return 0;
        }

        $created = 0;

        DB::table('tbSiparisSatir')
            ->orderBy('No')
            ->chunkById(250, function ($rows) use ($eventLogger, &$created) {
                foreach ($rows as $order) {
                    $orderItemNo = intval($order->No ?? 0);
                    $status = trim((string) ($order->Durum ?? ''));

                    if ($orderItemNo <= 0 || $status === '') {
                        continue;
                    }

                    $eventUuid = $this->deterministicUuid('seed-order-' . $orderItemNo);

                    if (WorkOrderEvent::query()->where('event_uuid', $eventUuid)->exists()
                        || WorkOrderEvent::query()->where('order_item_no', $orderItemNo)->exists()
                    ) {
                        continue;
                    }

                    $eventLogger->log([
                        'event_uuid' => $eventUuid,
                        'event_type' => 'state_seeded',
                        'aggregate_type' => 'order_item',
                        'aggregate_id' => $orderItemNo,
                        'order_item_no' => $orderItemNo,
                        'order_no' => trim((string) ($order->SiparisNo ?? '')) ?: null,
                        'work_order_no' => intval($order->GorevNo ?? 0) > 0 ? intval($order->GorevNo) : null,
                        'special_production_no' => intval($order->BagliOlduguOzelUretimNo ?? 0) > 0 ? intval($order->BagliOlduguOzelUretimNo) : null,
                        'status_after' => $status,
                        'title_human' => 'Mevcut siparis durumu merkeze aktarildi',
                        'summary_human' => 'Bu siparis icin gecmis event bulunamadigi icin mevcut durum Is Emri Merkezi icin baslangic kaydi olarak olusturuldu.',
                        'next_step_human' => null,
                        'source_screen' => 'Is Emri Merkezi Backfill',
                        'source_action' => 'Siparis Durumunu Tohumla',
                        'source_route' => 'tbSiparisSatir',
                        'actor_type' => 'system',
                        'actor_name' => 'Sistem',
                        'payload_after' => $this->serializeRecord($order),
                        'context' => [
                            'backfilled' => true,
                            'seeded_current_state' => true,
                        ],
                        'happened_at' => $order->GuncellemeTarihi ?? $order->IsEmriTarihi ?? $order->YuklemeTarihi ?? now(),
                    ]);

                    $created++;
                }
            }, 'No', 'No');

        return $created;
    }

    private function seedCurrentWorkOrdersWithoutEvents(WorkOrderEventLogger $eventLogger): int
    {
        if (!Schema::hasTable('tbGorevler')) {
            return 0;
        }

        $created = 0;

        DB::table('tbGorevler')
            ->orderBy('No')
            ->chunkById(250, function ($rows) use ($eventLogger, &$created) {
                foreach ($rows as $workOrder) {
                    $workOrderNo = intval($workOrder->No ?? 0);
                    if ($workOrderNo <= 0) {
                        continue;
                    }

                    $eventUuid = $this->deterministicUuid('seed-work-order-' . $workOrderNo);

                    if (WorkOrderEvent::query()->where('event_uuid', $eventUuid)->exists()
                        || WorkOrderEvent::query()->where('work_order_no', $workOrderNo)->exists()
                    ) {
                        continue;
                    }

                    $eventLogger->log([
                        'event_uuid' => $eventUuid,
                        'event_type' => 'state_seeded',
                        'aggregate_type' => 'work_order',
                        'aggregate_id' => $workOrderNo,
                        'order_item_no' => intval($workOrder->SiparisSatirNo ?? 0) > 0 ? intval($workOrder->SiparisSatirNo) : null,
                        'order_no' => trim((string) ($workOrder->SiparisNo ?? '')) ?: null,
                        'work_order_no' => $workOrderNo,
                        'status_after' => 'IsEmriVerildi',
                        'title_human' => 'Mevcut is emri merkeze aktarildi',
                        'summary_human' => 'Bu is emri icin gecmis event bulunamadigi icin mevcut durum baslangic kaydi olarak olusturuldu.',
                        'next_step_human' => 'Havuz ve personel gorevleri takip edilmeli.',
                        'source_screen' => 'Is Emri Merkezi Backfill',
                        'source_action' => 'Is Emri Durumunu Tohumla',
                        'source_route' => 'tbGorevler',
                        'actor_type' => 'system',
                        'actor_name' => 'Sistem',
                        'payload_after' => $this->serializeRecord($workOrder),
                        'context' => [
                            'backfilled' => true,
                            'seeded_current_state' => true,
                        ],
                        'happened_at' => now(),
                    ]);

                    $created++;
                }
            }, 'No', 'No');

        return $created;
    }

    private function projectCurrentSnapshots(WorkOrderSnapshotProjector $snapshotProjector): array
    {
        $projectedOrders = 0;
        $projectedWorkOrders = 0;

        if (Schema::hasTable('tbSiparisSatir')) {
            DB::table('tbSiparisSatir')
                ->orderBy('No')
                ->chunkById(250, function ($rows) use ($snapshotProjector, &$projectedOrders) {
                    foreach ($rows as $row) {
                        $orderItemNo = intval($row->No ?? 0);
                        if ($orderItemNo <= 0) {
                            continue;
                        }

                        $snapshotProjector->projectOrderItem($orderItemNo);
                        $projectedOrders++;
                    }
                }, 'No', 'No');
        }

        if (Schema::hasTable('tbGorevler')) {
            DB::table('tbGorevler')
                ->orderBy('No')
                ->chunkById(250, function ($rows) use ($snapshotProjector, &$projectedWorkOrders) {
                    foreach ($rows as $row) {
                        $workOrderNo = intval($row->No ?? 0);
                        if ($workOrderNo <= 0) {
                            continue;
                        }

                        $snapshotProjector->projectWorkOrder($workOrderNo);
                        $projectedWorkOrders++;
                    }
                }, 'No', 'No');
        }

        return [$projectedOrders, $projectedWorkOrders];
    }

    private function resolveAggregate(int $orderItemNo, int $workOrderNo): array
    {
        if ($orderItemNo > 0) {
            return [
                'aggregate_type' => 'order_item',
                'aggregate_id' => $orderItemNo,
                'order_item_no' => $orderItemNo,
            ];
        }

        if ($workOrderNo > 0) {
            return [
                'aggregate_type' => 'work_order',
                'aggregate_id' => $workOrderNo,
                'order_item_no' => null,
            ];
        }

        return [
            'aggregate_type' => 'legacy_history',
            'aggregate_id' => 1,
            'order_item_no' => null,
        ];
    }

    private function mapLegacyOperation(string $operation): array
    {
        $normalized = strtolower(trim($operation));
        $slug = str_replace([' ', '-', '.'], '_', $normalized);

        return match ($normalized) {
            'isemriverildi', 'verildi' => [
                'event_type' => 'work_order_created_single',
                'title_human' => 'Legacy kayittan is emri verildi bilgisi aktarıldı',
                'status_before' => 'UretimBekliyor',
                'status_after' => 'IsEmriVerildi',
                'next_step_human' => 'Havuz veya personel gorevi takibi yapilmali.',
            ],
            'iptaledildi', 'iptal edildi' => [
                'event_type' => 'work_order_cancelled',
                'title_human' => 'Legacy kayittan is emri iptali aktarıldı',
                'status_before' => 'IsEmriVerildi',
                'status_after' => 'UretimBekliyor',
                'next_step_human' => 'Siparis tekrar planlamaya alinabilir.',
            ],
            'stokkarsilandi', 'stoktan dusuldu', 'stoktan düşüldü' => [
                'event_type' => 'stock_deducted',
                'title_human' => 'Legacy kayittan stok karsilama bilgisi aktarıldı',
                'status_before' => null,
                'status_after' => 'StokKarsilandi',
                'next_step_human' => 'Kayit stoktan kapanmis durumda.',
            ],
            default => [
                'event_type' => 'legacy_' . ($slug !== '' ? $slug : 'event'),
                'title_human' => 'Legacy kayit merkeze aktarıldı',
                'status_before' => null,
                'status_after' => null,
                'next_step_human' => null,
            ],
        };
    }

    private function buildLegacySummary(object $row, string $title): string
    {
        $orderNo = trim((string) ($row->SiparisNo ?? ''));
        $product = trim((string) ($row->UrunAdi ?? ''));
        $when = $row->IslemTarihi ? date('d.m.Y H:i', strtotime((string) $row->IslemTarihi)) : 'bilinmeyen tarihte';

        $summary = $when . ' tarihinde legacy sistemde "' . $title . '" kaydi bulundu.';

        if ($orderNo !== '') {
            $summary .= ' Siparis: ' . $orderNo . '.';
        }

        if ($product !== '') {
            $summary .= ' Urun: ' . $product . '.';
        }

        return $summary;
    }

    private function supportsCenterTables(): bool
    {
        return Schema::hasTable('work_order_events') && Schema::hasTable('work_order_snapshots');
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function serializeRecord(object|array|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true);
    }
}
