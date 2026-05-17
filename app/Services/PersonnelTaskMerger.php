<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PersonnelTaskMerger
{
    /**
     * Aynı personele aynı gün verilen aynı açık görevi tek satırda toplar.
     *
     * ASP.NET akışı tarih değiştirirken aynı ara ürün ve tarihteki kayıtları tek satırda
     * topluyordu. Laravel ekranında da aynı okuma için görünen AraUrunAdi adı esas alınır;
     * UrunIDNo anahtara dahil edilmez. SiparisSatirNo dahildir çünkü farklı siparişlerin
     * görevlerinin birleştirilmesi sipariş izini koparır.
     */
    public function mergeOpenDuplicatesForPersonnel(int $personelNo): void
    {
        if ($personelNo <= 0 || !$this->hasRequiredSchema()) {
            return;
        }

        $columns = $this->availableColumns();
        $has = array_fill_keys($columns, true);
        $hasComponentName = Schema::hasTable('tbAraUrun')
            && Schema::hasColumn('tbAraUrun', 'No')
            && Schema::hasColumn('tbAraUrun', 'AraUrunAdi');

        DB::transaction(function () use ($personelNo, $columns, $has, $hasComponentName) {
            $query = DB::table('tbPersonelGorev as pg')
                ->where('pg.PersonelNo', $personelNo)
                ->where(function ($query) {
                    $query->where('pg.Adet', '>', 0)
                        ->orWhere('pg.BekleyenAdet', '>', 0);
                })
                ->orderBy('pg.No')
                ->lockForUpdate();

            if ($hasComponentName) {
                $query->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No');
            }

            $rows = $query->select($this->selectColumns($columns, $hasComponentName))->get();

            if ($rows->count() < 2) {
                return;
            }

            $groups = [];
            foreach ($rows as $row) {
                $total = max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
                if ($total <= 0) {
                    continue;
                }

                $groups[$this->groupKey($row, $has)][] = $row;
            }

            foreach ($groups as $groupRows) {
                if (count($groupRows) < 2) {
                    continue;
                }

                $this->mergeGroup($groupRows);
            }
        });
    }

    private function hasRequiredSchema(): bool
    {
        if (!Schema::hasTable('tbPersonelGorev')) {
            return false;
        }

        foreach (['No', 'PersonelNo', 'GorevBaslamaTarihi', 'Adet', 'BekleyenAdet', 'AraUrunAdiNo'] as $column) {
            if (!Schema::hasColumn('tbPersonelGorev', $column)) {
                return false;
            }
        }

        return true;
    }

    private function availableColumns(): array
    {
        $columns = ['No', 'PersonelNo', 'GorevBaslamaTarihi', 'Adet', 'BekleyenAdet', 'AraUrunAdiNo'];

        foreach (['UrunIDNo', 'BolumAdiNo', 'Onay', 'SiparisSatirNo', 'SiparisNo'] as $column) {
            if (Schema::hasColumn('tbPersonelGorev', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function selectColumns(array $columns, bool $hasComponentName): array
    {
        $selected = array_map(fn (string $column) => "pg.{$column} as {$column}", $columns);
        $selected[] = $hasComponentName
            ? 'au.AraUrunAdi as AraUrunAdi'
            : DB::raw("'' as AraUrunAdi");

        return $selected;
    }

    private function groupKey(object $row, array $has): string
    {
        $componentKey = $this->componentKey($row);

        return implode("\x1F", [
            intval($row->PersonelNo ?? 0),
            $componentKey,
            ($has['BolumAdiNo'] ?? false) ? intval($row->BolumAdiNo ?? 0) : 0,
            $this->dateKey($row->GorevBaslamaTarihi ?? ''),
            $this->approvalPhase($row, $has['Onay'] ?? false),
            // Sipariş izini koruma — farklı siparişlerin görevleri birleştirilmez
            ($has['SiparisSatirNo'] ?? false) ? intval($row->SiparisSatirNo ?? 0) : 0,
        ]);
    }

    private function componentKey(object $row): string
    {
        $name = $this->normalizeName($row->AraUrunAdi ?? '');
        if ($name !== '') {
            return 'name:' . $name;
        }

        return 'no:' . intval($row->AraUrunAdiNo ?? 0);
    }

    private function normalizeName(mixed $value): string
    {
        $name = strtolower(trim((string) $value));
        $name = strtr($name, [
            'ı' => 'i',
            'İ' => 'i',
            'ğ' => 'g',
            'Ğ' => 'g',
            'ü' => 'u',
            'Ü' => 'u',
            'ş' => 's',
            'Ş' => 's',
            'ö' => 'o',
            'Ö' => 'o',
            'ç' => 'c',
            'Ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $name) ?? '';
    }

    private function dateKey(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        if (preg_match('/^(\d{2})[\/.](\d{2})[\/.](\d{4})/', $raw, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        return substr($raw, 0, 10);
    }

    private function approvalPhase(object $row, bool $hasOnay): string
    {
        if (!$hasOnay) {
            return 'active';
        }

        $value = strtolower(trim((string) ($row->Onay ?? '')));
        if (in_array($value, ['hazir', 'ready'], true)) {
            return 'ready';
        }

        if (in_array($value, ['1', 'true', 'evet', 'yes'], true)) {
            return 'approved';
        }

        if (in_array($value, ['0', 'false', 'hayir', 'hayır', 'no'], true)) {
            return 'active';
        }

        $adet = max(0, intval($row->Adet ?? 0));
        $bekleyen = max(0, intval($row->BekleyenAdet ?? 0));
        if ($value === '' && $adet > 0) {
            return 'active';
        }

        if ($value === '' && $bekleyen > 0) {
            return 'waiting';
        }

        return 'other:' . $value;
    }

    /**
     * @param array<int, object> $rows
     */
    private function mergeGroup(array $rows): void
    {
        $primary = $rows[0];
        $primaryNo = intval($primary->No ?? 0);
        if ($primaryNo <= 0) {
            return;
        }

        $duplicateIds = [];
        $totalAdet = 0;
        $totalBekleyenAdet = 0;

        foreach ($rows as $row) {
            $rowNo = intval($row->No ?? 0);
            $totalAdet += max(0, intval($row->Adet ?? 0));
            $totalBekleyenAdet += max(0, intval($row->BekleyenAdet ?? 0));

            if ($rowNo > 0 && $rowNo !== $primaryNo) {
                $duplicateIds[] = $rowNo;
            }
        }

        $total = $totalAdet + $totalBekleyenAdet;
        $split = app(BomService::class)->personnelTaskReadySplit($primary, $total);

        DB::table('tbPersonelGorev')
            ->where('No', $primaryNo)
            ->update([
                'Adet' => intval($split['ready']),
                'BekleyenAdet' => intval($split['waiting']),
            ]);

        if (!empty($duplicateIds)) {
            DB::table('tbPersonelGorev')->whereIn('No', $duplicateIds)->delete();
        }
    }
}
