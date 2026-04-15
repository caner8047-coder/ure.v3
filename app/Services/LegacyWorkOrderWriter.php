<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyWorkOrderWriter
{
    private ?array $taskTableColumns = null;

    public function formatDateTime(): string
    {
        return now()->format('d/m/Y H:i');
    }

    public function formatDate(): string
    {
        return now()->format('d/m/Y');
    }

    public function formatTime(): string
    {
        return now()->format('H:i');
    }

    public function insertLegacyWorkOrder(array $attributes): int
    {
        return DB::transaction(function () use ($attributes) {
            $columns = $this->getTaskTableColumns();
            $row = DB::table('tbGorevler')
                ->selectRaw('COALESCE(MAX(No), 0) AS max_no')
                ->lockForUpdate()
                ->first();

            $nextNo = (int) ($row->max_no ?? 0) + 1;
            $startedAt = $attributes['GorevBaslamaTarihi'] ?? $this->formatDateTime();
            $finishedAt = $attributes['GorevBitisTarihi'] ?? $startedAt;

            $payload = [
                'No' => $nextNo,
                'UrunIDNo' => $attributes['UrunIDNo'] ?? null,
                'GorevBaslamaTarihi' => $startedAt,
                'GorevBitisTarihi' => $finishedAt,
                'ToplamAdet' => (int) ($attributes['ToplamAdet'] ?? 0),
                'BolumAdiNo' => !empty($attributes['BolumAdiNo']) ? (int) $attributes['BolumAdiNo'] : null,
                'Performans' => $attributes['Performans'] ?? 0,
                'AraUrunAdiNo' => !empty($attributes['AraUrunAdiNo']) ? (int) $attributes['AraUrunAdiNo'] : null,
                'UretilenAdet' => (int) ($attributes['UretilenAdet'] ?? 0),
                'PersonelNo' => !empty($attributes['PersonelNo']) ? (int) $attributes['PersonelNo'] : null,
            ];

            DB::table('tbGorevler')->insert(array_intersect_key($payload, array_flip($columns)));

            return $nextNo;
        });
    }

    private function getTaskTableColumns(): array
    {
        if ($this->taskTableColumns === null) {
            $this->taskTableColumns = Schema::getColumnListing('tbGorevler');
        }

        return $this->taskTableColumns;
    }
}
