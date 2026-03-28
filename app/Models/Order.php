<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'tbVerilenGorevler';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'UrunIDNo', 'GorevTarihi', 'ToplamAdet', 'Aciklama'
    ];
}
