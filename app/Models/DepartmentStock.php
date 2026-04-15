<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DepartmentStock extends Model
{
    protected $table = 'tbBolumAraStok';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'BolumAdiNo', 'Adet', 'AraUrunAdiNo', 'UrunIDNo', 'TamponMiktar'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'BolumAdiNo', 'No');
    }

    public function component()
    {
        return $this->belongsTo(Component::class, 'AraUrunAdiNo', 'No');
    }
}
