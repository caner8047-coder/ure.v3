<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductionPool extends Model
{
    protected $table = 'tbBolumHavuz';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'UrunIDNo', 'GorevBaslangicTarihi', 'BolumAdiNo', 'AraUrunAdiNo',
        'Adet', 'ToplamAdet', 'AdimSirasi'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'BolumAdiNo', 'bolum_no');
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
