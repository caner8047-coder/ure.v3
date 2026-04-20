<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

echo "=== Orphan Veri Temizleme Başlıyor ===\n\n";

$summary = [];

// 1. tbPersonelGorev — eksik personel referansları
$missingPersonnel = DB::table('tbPersonelGorev as pg')
    ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
    ->whereNotNull('pg.PersonelNo')
    ->where('pg.PersonelNo', '>', 0)
    ->whereNull('p.PersonelNo')
    ->pluck('pg.No')
    ->all();

if (!empty($missingPersonnel)) {
    $count = DB::table('tbPersonelGorev')->whereIn('No', $missingPersonnel)->delete();
    $summary[] = "tbPersonelGorev (eksik personel): {$count} kayıt silindi.";
    echo "[✓] {$count} orphan personel görevi silindi.\n";
} else {
    echo "[✓] tbPersonelGorev personel referansları temiz.\n";
}

// 2. tbPersonelGorev — eksik component referansları
$missingComponent = DB::table('tbPersonelGorev as pg')
    ->leftJoin('tbAraUrun as a', 'pg.AraUrunAdiNo', '=', 'a.No')
    ->whereNotNull('pg.AraUrunAdiNo')
    ->whereNull('a.No')
    ->pluck('pg.No')
    ->all();

if (!empty($missingComponent)) {
    $count = DB::table('tbPersonelGorev')->whereIn('No', $missingComponent)->delete();
    $summary[] = "tbPersonelGorev (eksik component): {$count} kayıt silindi.";
    echo "[✓] {$count} orphan component görevi silindi.\n";
} else {
    echo "[✓] tbPersonelGorev component referansları temiz.\n";
}

// 3. tbBolumHavuz — eksik component referansları
$missingHavuzComponent = DB::table('tbBolumHavuz as bh')
    ->leftJoin('tbAraUrun as a', 'bh.AraUrunAdiNo', '=', 'a.No')
    ->whereNotNull('bh.AraUrunAdiNo')
    ->whereNull('a.No')
    ->pluck('bh.No')
    ->all();

if (!empty($missingHavuzComponent)) {
    $count = DB::table('tbBolumHavuz')->whereIn('No', $missingHavuzComponent)->delete();
    $summary[] = "tbBolumHavuz (eksik component): {$count} kayıt silindi.";
    echo "[✓] {$count} orphan havuz kaydı silindi.\n";
} else {
    echo "[✓] tbBolumHavuz component referansları temiz.\n";
}

// 4. tbBolumHavuz — eksik bölüm referansları
$missingHavuzDepartment = DB::table('tbBolumHavuz as bh')
    ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
    ->whereNotNull('bh.BolumAdiNo')
    ->whereNull('b.No')
    ->pluck('bh.No')
    ->all();

if (!empty($missingHavuzDepartment)) {
    $count = DB::table('tbBolumHavuz')->whereIn('No', $missingHavuzDepartment)->delete();
    $summary[] = "tbBolumHavuz (eksik bölüm): {$count} kayıt silindi.";
    echo "[✓] {$count} orphan bölüm havuz kaydı silindi.\n";
} else {
    echo "[✓] tbBolumHavuz bölüm referansları temiz.\n";
}

// 5. tbSiparisSatir — eksik Nihai ürün referansları
$missingOrderProduct = DB::table('tbSiparisSatir as s')
    ->leftJoin('tbUrunler as u', 's.EslesenUrunNo', '=', 'u.No')
    ->where('s.EslesenUrunTur', 'Nihai')
    ->whereNotNull('s.EslesenUrunNo')
    ->whereNull('u.No')
    ->pluck('s.No')
    ->all();

if (!empty($missingOrderProduct)) {
    $count = DB::table('tbSiparisSatir')->whereIn('No', $missingOrderProduct)->update([
        'EslesenUrunNo' => null,
        'EslesenUrunTur' => null,
        'EslesmePuani' => null,
        'EslesmeYontemi' => null,
        'Durum' => 'UretimBekliyor',
    ]);
    $summary[] = "tbSiparisSatir (eksik ürün): {$count} kayıt sıfırlandı.";
    echo "[✓] {$count} orphan sipariş satırı eşleşmesi sıfırlandı.\n";
} else {
    echo "[✓] tbSiparisSatir ürün referansları temiz.\n";
}

// 6. tbSiparisSatir — eksik Ara component referansları
$missingOrderComponent = DB::table('tbSiparisSatir as s')
    ->leftJoin('tbAraUrun as a', 's.EslesenUrunNo', '=', 'a.No')
    ->where('s.EslesenUrunTur', 'Ara')
    ->whereNotNull('s.EslesenUrunNo')
    ->whereNull('a.No')
    ->pluck('s.No')
    ->all();

if (!empty($missingOrderComponent)) {
    $count = DB::table('tbSiparisSatir')->whereIn('No', $missingOrderComponent)->update([
        'EslesenUrunNo' => null,
        'EslesenUrunTur' => null,
        'EslesmePuani' => null,
        'EslesmeYontemi' => null,
        'Durum' => 'UretimBekliyor',
    ]);
    $summary[] = "tbSiparisSatir (eksik component): {$count} kayıt sıfırlandı.";
    echo "[✓] {$count} orphan sipariş satırı component eşleşmesi sıfırlandı.\n";
} else {
    echo "[✓] tbSiparisSatir component referansları temiz.\n";
}

echo "\n=== Temizleme Tamamlandı ===\n";
echo "Toplam: " . count($summary) . " işlem yapıldı.\n";

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
