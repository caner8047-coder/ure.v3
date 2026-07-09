<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillMissingStockRows extends Command
{
    protected $signature = 'stocks:backfill-missing-rows
        {--apply : Write missing zero-quantity stock rows}
        {--sample=20 : Number of missing rows to print}';

    protected $description = 'Create tbBolumAraStok rows for components that appear in stock inventory without a stock row';

    public function handle(): int
    {
        if (!Schema::hasTable('tbAraUrun') || !Schema::hasTable('tbBolumAraStok')) {
            $this->error('Required legacy stock tables were not found.');
            return self::FAILURE;
        }

        $missingRows = $this->missingRows()->get();
        $sampleLimit = max(1, intval($this->option('sample')));

        foreach ($missingRows->take($sampleLimit) as $row) {
            $this->line(sprintf(
                '#%d | bolum=%s | %s | %s',
                intval($row->AraUrunAdiNo),
                $row->BolumAdiNo === null ? '-' : intval($row->BolumAdiNo),
                (string) ($row->AraUrunAdi ?? ''),
                (string) ($row->UrunCesidi ?? '')
            ));
        }

        if (!$this->option('apply')) {
            $this->info('Dry-run: ' . $missingRows->count() . ' missing stock rows found.');
            $this->line('Apply with: php artisan stocks:backfill-missing-rows --apply');
            return self::SUCCESS;
        }

        $created = 0;

        DB::transaction(function () use ($missingRows, &$created) {
            foreach ($missingRows as $row) {
                $componentNo = intval($row->AraUrunAdiNo ?? 0);
                if ($componentNo <= 0) {
                    continue;
                }

                $alreadyExists = DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', $componentNo)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                DB::table('tbBolumAraStok')->insert([
                    'BolumAdiNo' => $row->BolumAdiNo === null ? null : intval($row->BolumAdiNo),
                    'Adet' => 0,
                    'AraUrunAdiNo' => $componentNo,
                    'UrunIDNo' => 0,
                    'TamponMiktar' => 0,
                ]);

                $created++;
            }
        });

        $this->info($created . ' missing stock rows created with unique stock numbers.');

        return self::SUCCESS;
    }

    private function missingRows()
    {
        return DB::table('tbAraUrun as a')
            ->leftJoin('tbBolumAraStok as s', 's.AraUrunAdiNo', '=', 'a.No')
            ->whereNull('s.No')
            ->whereNotNull('a.AraUrunAdi')
            ->whereRaw("TRIM(COALESCE(a.AraUrunAdi, '')) <> ''")
            ->orderBy('a.No')
            ->select(
                DB::raw('a.No as AraUrunAdiNo'),
                'a.BolumAdiNo',
                'a.AraUrunAdi',
                'a.UrunCesidi'
            );
    }
}
