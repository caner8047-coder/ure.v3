<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    protected $table = 'tbAraUrun';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'AraUrunAdi', 'Performans', 'BolumAdiNo', 'MinAdet', 'UrunCesidi', 'Yol', 'Resim'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'BolumAdiNo', 'No');
    }

    public function stocks()
    {
        return $this->hasMany(DepartmentStock::class, 'AraUrunAdiNo', 'No');
    }

    public function pools()
    {
        return $this->hasMany(ProductionPool::class, 'AraUrunAdiNo', 'No');
    }
}
