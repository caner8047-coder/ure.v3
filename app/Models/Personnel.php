<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    protected $table = 'tbPersonel';
    protected $primaryKey = 'PersonelNo';
    public $timestamps = false;

    protected $fillable = [
        'Ad', 'Soyad', 'Mail', 'Sifre', 'BolumAdiNo'
    ];

    protected $hidden = ['Sifre'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'BolumAdiNo', 'bolum_no');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'PersonelNo', 'PersonelNo');
    }
}
