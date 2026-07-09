<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkOrderNarrationService
{
    public function buildSnapshotNarration(array $snapshot, Collection $timeline): array
    {
        $currentStatus = trim((string) ($snapshot['current_status'] ?? ''));
        $currentStage = trim((string) ($snapshot['current_stage'] ?? ''));
        $holder = trim((string) ($snapshot['current_holder_name'] ?? ''));
        $next = trim((string) ($snapshot['next_expected_action'] ?? ''));

        $sentences = [];

        if ($currentStatus !== '') {
            $sentences[] = 'Bu kaydin guncel durumu ' . $this->humanizeStatus($currentStatus) . '.';
        }

        if ($currentStage !== '') {
            $sentences[] = 'Sistem bu kaydi su anda "' . $this->humanizeStage($currentStage) . '" asamasinda goruyor.';
        }

        if ($holder !== '') {
            $sentences[] = 'Kayit su anda en cok "' . $holder . '" ile iliskili gorunuyor.';
        }

        if ($next !== '') {
            $sentences[] = 'Sonraki beklenen adim: ' . $next . '.';
        }

        $latestEvent = $timeline->first();
        if ($latestEvent) {
            $eventTitle = trim((string) ($latestEvent->title_human ?? ''));
            $eventTime = $this->formatDateTime($latestEvent->happened_at ?? null);

            if ($eventTitle !== '') {
                $sentences[] = ($eventTime !== '' ? $eventTime . ' tarihinde ' : '')
                    . 'en son "' . $eventTitle . '" olayi kaydedildi.';
            }
        }

        $short = trim(implode(' ', array_slice($sentences, 0, 3)));
        $long = trim(implode(' ', $sentences));

        return [
            'short' => $short !== '' ? $short : 'Bu kayit icin yeterli olay ozeti bulunamadi.',
            'long' => $long !== '' ? $long : 'Bu kayit icin yeterli olay ozeti bulunamadi.',
        ];
    }

    public function humanizeStatus(?string $status): string
    {
        return match ((string) $status) {
            'UretimBekliyor' => 'Uretim Bekliyor',
            'IsEmriVerildi' => 'Is Emri Verildi',
            'UretimdenKarsilaniyor' => 'Uretimden Karsilaniyor',
            'StokKarsilandi' => 'Stoktan Karsilandi',
            'PasifDevamEden' => 'Pasif Ama Uretimi Suruyor',
            'Pasif' => 'Pasif',
            default => trim((string) $status) !== '' ? (string) $status : 'Bilinmiyor',
        };
    }

    public function humanizeStage(?string $stage): string
    {
        return match ((string) $stage) {
            'waiting' => 'islem bekliyor',
            'issued' => 'is emri acildi',
            'in_pool' => 'havuzda bekliyor',
            'in_production' => 'uretimde',
            'ready_for_production' => 'uretime hazir',
            'assigned_waiting' => 'atanmis gorev bekliyor',
            'linked_to_special_production' => 'ozel uretime bagli',
            'fulfilled_from_stock' => 'stoktan karsilanmis',
            'cancelled' => 'kapatilmis',
            'cancelled_but_processing_tail' => 'iptal edilmis ama kuyrugu suruyor',
            default => trim((string) $stage) !== '' ? (string) $stage : 'bilinmeyen asama',
        };
    }

    public function buildTimelineInsights(Collection $timeline): array
    {
        $eventCount = $timeline->count();
        $actorNames = $timeline->pluck('actor_name')->filter()->unique()->values();
        $sourceScreens = $timeline->pluck('source_screen')->filter()->unique()->values();
        $backfilledCount = $timeline->filter(fn ($event) => boolval(data_get($event->context, 'backfilled', false)))->count();
        $seededCount = $timeline->filter(fn ($event) => boolval(data_get($event->context, 'seeded_current_state', false)))->count();
        $systemCount = $timeline->filter(fn ($event) => ($event->actor_type ?? '') === 'system')->count();
        $latestEvent = $timeline->first();
        $oldestEvent = $timeline->last();

        $storyBits = [];
        if ($eventCount > 0) {
            $storyBits[] = 'Bu kayit icin ' . $eventCount . ' olay kaydi bulundu.';
        }

        if ($latestEvent) {
            $latestTitle = trim((string) ($latestEvent->title_human ?? ''));
            $latestTime = $this->formatDateTime($latestEvent->happened_at ?? null);
            if ($latestTitle !== '') {
                $storyBits[] = ($latestTime !== '' ? $latestTime . ' tarihinde ' : '') . 'en son "' . $latestTitle . '" kaydi olustu.';
            }
        }

        if ($oldestEvent && $eventCount > 1) {
            $oldestTime = $this->formatDateTime($oldestEvent->happened_at ?? null);
            if ($oldestTime !== '') {
                $storyBits[] = 'Gorebildigimiz ilk hareket ' . $oldestTime . ' tarihine uzaniyor.';
            }
        }

        if ($backfilledCount > 0) {
            $storyBits[] = $backfilledCount . ' olay legacy kayitlardan merkeze aktarildi.';
        }

        if ($seededCount > 0) {
            $storyBits[] = $seededCount . ' kayit gecmis olmadigi icin mevcut durumdan tohumlandi.';
        }

        return [
            'event_count' => $eventCount,
            'unique_actor_count' => $actorNames->count(),
            'actors' => $actorNames->take(5)->values(),
            'source_screens' => $sourceScreens->take(5)->values(),
            'backfilled_count' => $backfilledCount,
            'seeded_count' => $seededCount,
            'system_event_count' => $systemCount,
            'latest_event_at' => $this->formatDateTime($latestEvent?->happened_at),
            'first_event_at' => $this->formatDateTime($oldestEvent?->happened_at),
            'story' => trim(implode(' ', $storyBits)),
        ];
    }

    public function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }
}
