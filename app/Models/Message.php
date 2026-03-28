<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'tbIletisim';
    protected $primaryKey = 'MesajNo';
    public $timestamps = false;

    protected $fillable = ['PersonelNo', 'BolumAdiNo', 'Mesaj', 'Tarih', 'Okundu'];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'PersonelNo', 'PersonelNo');
    }
}
