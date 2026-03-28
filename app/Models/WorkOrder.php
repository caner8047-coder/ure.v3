<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    protected $table = 'tbGorevler';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'UrunIDNo', 'GorevBaslamaTarihi', 'ToplamAdet', 'BolumAdiNo', 'PersonelNo'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'UrunIDNo', 'No');
    }
}
