<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tbPersonelGorev';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'UrunIDNo', 'PersonelNo', 'Adet', 'BekleyenAdet', 'Onay',
        'AraUrunAdiNo', 'BolumAdiNo', 'GorevBaslamaTarihi'
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'PersonelNo', 'PersonelNo');
    }

    public function component()
    {
        return $this->belongsTo(Component::class, 'AraUrunAdiNo', 'No');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'UrunIDNo', 'No');
    }
}
