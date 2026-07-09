<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeBomComponentTypes extends Command
{
    protected $signature = 'legacy:normalize-bom-types
        {--dry-run : Show changes without writing to the database}
        {--sample=30 : Number of changed rows to print}';

    protected $description = 'Normalize tbAraUrun.UrunCesidi from the BOM graph';

    public function handle(): int
    {
        $rows = DB::table('tbAraUrun')
            ->select('No', 'AraUrunAdi', 'Yol', 'UrunCesidi')
            ->orderBy('No')
            ->get();

        $parentNos = [];
        foreach ($rows as $row) {
            foreach ($this->childNos((string) ($row->Yol ?? '')) as $childNo) {
                $parentNos[$childNo] = true;
            }
        }

        $changes = [];
        foreach ($rows as $row) {
            $no = (int) $row->No;
            $expectedType = $this->expectedType((string) ($row->Yol ?? ''), isset($parentNos[$no]));
            $currentType = trim((string) ($row->UrunCesidi ?? ''));

            if ($currentType !== $expectedType) {
                $changes[] = [
                    'no' => $no,
                    'name' => (string) ($row->AraUrunAdi ?? ''),
                    'from' => $currentType,
                    'to' => $expectedType,
                ];
            }
        }

        $sampleLimit = max(1, (int) $this->option('sample'));
        foreach (array_slice($changes, 0, $sampleLimit) as $change) {
            $this->line(sprintf(
                '#%d %s: "%s" -> "%s"',
                $change['no'],
                $change['name'],
                $change['from'] !== '' ? $change['from'] : 'BOŞ',
                $change['to']
            ));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run: ' . count($changes) . ' satır güncellenecek.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                DB::table('tbAraUrun')
                    ->where('No', $change['no'])
                    ->update(['UrunCesidi' => $change['to']]);
            }
        });

        $this->info(count($changes) . ' satır BOM kuralına göre güncellendi.');

        return self::SUCCESS;
    }

    private function childNos(string $path): array
    {
        $childNos = [];
        foreach (explode(':', $path) as $part) {
            if (preg_match('/^\s*(\d+)\s*-/', $part, $matches)) {
                $childNos[] = (int) $matches[1];
            }
        }

        return $childNos;
    }

    private function expectedType(string $path, bool $hasParent): string
    {
        $hasChildren = trim($path) !== '';

        if (!$hasChildren) {
            return 'Ham Madde';
        }

        if (!$hasParent) {
            return 'Nihayi Ürün';
        }

        return 'Ara Mamül';
    }
}
