<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'tbSiparisSatir';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'SiparisNo', 'Pazaryeri', 'Magaza', 'SiparisTarihi', 'Musteri',
        'UrunAdi', 'Adet', 'MusteriNotu', 'KargoSonTeslim', 'Kategori',
        'Durum', 'Aktif', 'EslesenUrunNo', 'EslesenUrunTur', 'EslesmePuani',
        'EslesmeYontemi', 'IsEmriTarihi', 'GorevNo', 'StokKodu',
        'SetMi', 'SetNo', 'AnaSetSatirNo', 'TamponDusumleri',
        'BagliOlduguOzelUretimNo', 'YuklemeTarihi', 'GuncellemeTarihi'
    ];

    protected $casts = [
        'Aktif' => 'boolean',
        'SetMi' => 'boolean',
        'SiparisTarihi' => 'datetime',
        'KargoSonTeslim' => 'datetime',
        'IsEmriTarihi' => 'datetime',
    ];
}
