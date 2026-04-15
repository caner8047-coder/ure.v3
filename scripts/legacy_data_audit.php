<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$audit = [
    'generated_at' => now()->toDateTimeString(),
    'counts' => [
        'tbPersonel' => DB::table('tbPersonel')->count(),
        'tbSiparisSatir' => DB::table('tbSiparisSatir')->count(),
        'tbBolumHavuz' => DB::table('tbBolumHavuz')->count(),
        'tbPersonelGorev' => DB::table('tbPersonelGorev')->count(),
        'tbGorevler' => DB::table('tbGorevler')->count(),
        'tbBolumAraStok' => DB::table('tbBolumAraStok')->count(),
        'users' => DB::table('users')->count(),
    ],
    'orphans' => [
        'tbPersonelGorev_missing_personnel' => DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->whereNotNull('pg.PersonelNo')
            ->where('pg.PersonelNo', '>', 0)
            ->whereNull('p.PersonelNo')
            ->count(),
        'tbPersonelGorev_missing_component' => DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as a', 'pg.AraUrunAdiNo', '=', 'a.No')
            ->whereNotNull('pg.AraUrunAdiNo')
            ->whereNull('a.No')
            ->count(),
        'tbBolumHavuz_missing_component' => DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
            ->whereNotNull('bh.AraUrunAdiNo')
            ->whereNull('a.No')
            ->count(),
        'tbBolumHavuz_missing_department' => DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->whereNotNull('bh.BolumAdiNo')
            ->whereNull('b.No')
            ->count(),
        'tbSiparisSatir_missing_product' => DB::table('tbSiparisSatir as s')
            ->leftJoin('tbUrunler as u', 's.EslesenUrunNo', '=', 'u.No')
            ->where('s.EslesenUrunTur', 'Nihai')
            ->whereNotNull('s.EslesenUrunNo')
            ->whereNull('u.No')
            ->count(),
        'tbSiparisSatir_missing_component' => DB::table('tbSiparisSatir as s')
            ->leftJoin('tbAraUrun as a', 's.EslesenUrunNo', '=', 'a.No')
            ->where('s.EslesenUrunTur', 'Ara')
            ->whereNotNull('s.EslesenUrunNo')
            ->whereNull('a.No')
            ->count(),
    ],
    'status_breakdown' => DB::table('tbSiparisSatir')
        ->select('Durum', DB::raw('COUNT(*) as adet'))
        ->groupBy('Durum')
        ->orderByDesc('adet')
        ->get(),
    'task_onay_breakdown' => DB::table('tbPersonelGorev')
        ->select('Onay', DB::raw('COUNT(*) as adet'))
        ->groupBy('Onay')
        ->orderByDesc('adet')
        ->get(),
    'samples' => [
        'missing_personnel' => DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->whereNotNull('pg.PersonelNo')
            ->where('pg.PersonelNo', '>', 0)
            ->whereNull('p.PersonelNo')
            ->select('pg.No', 'pg.PersonelNo', 'pg.UrunIDNo', 'pg.AraUrunAdiNo', 'pg.BolumAdiNo')
            ->limit(10)
            ->get(),
        'missing_component_in_tasks' => DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as a', 'pg.AraUrunAdiNo', '=', 'a.No')
            ->whereNotNull('pg.AraUrunAdiNo')
            ->whereNull('a.No')
            ->select('pg.No', 'pg.PersonelNo', 'pg.UrunIDNo', 'pg.AraUrunAdiNo', 'pg.BolumAdiNo')
            ->limit(10)
            ->get(),
    ],
];

$audit['ok'] = collect($audit['orphans'])->every(fn ($count) => intval($count) === 0);

echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($audit['ok'] ? 0 : 1);
