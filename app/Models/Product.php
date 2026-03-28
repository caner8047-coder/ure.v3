<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'tbUrunler';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = [
        'UrunID', 'AraAdlarYol', 'SistemAdi', 'SistemKodu', 'Resim'
    ];
}
