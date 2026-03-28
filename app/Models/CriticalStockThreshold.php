<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriticalStockThreshold extends Model
{
    protected $table = 'tbKritikStokEsik';
    protected $primaryKey = 'No';
    public $timestamps = false; // Original table uses custom OlusturmaTarihi, etc.

    protected $fillable = [
        'AraUrunAdiNo',
        'EsikMiktar',
        'OtomatikIsEmri',
        'IsEmriAdet',
        'UrunIDNo',
        'Aktif',
        'SonKontrolTarihi',
        'SonUyariTarihi',
        'OlusturmaTarihi'
    ];

    public function component()
    {
        return $this->belongsTo(Component::class, 'AraUrunAdiNo', 'No');
    }
}
