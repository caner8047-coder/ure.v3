<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ord = DB::table('tbSiparisSatir')->where('SiparisNo', 'STOK-20260415-9789')->first();
if(!$ord) die("Siparis is not found\n");
echo "Satir No: {$ord->No}\n";
echo "Musteri: {$ord->Musteri}\n";

$pGorev = DB::table('tbPersonelGorev')->where('SiparisSatirNo', $ord->No)->get();
echo "PGorev count: " . count($pGorev) . "\n";

// Now query everything for the matched product
$pgAll = DB::select("SELECT h.SiparisSatirNo, s.Musteri, h.Adet FROM tbPersonelGorev h LEFT JOIN tbSiparisSatir s ON h.SiparisSatirNo = s.No WHERE h.AraUrunAdiNo = " . $ord->EslesenUrunNo);
echo "PGorev for this product total rows: " . count($pgAll) . "\n";
foreach($pgAll as $r) {
    echo "  SipSatirNo: {$r->SiparisSatirNo}, Musteri: {$r->Musteri}, Adet: {$r->Adet}\n";
}
