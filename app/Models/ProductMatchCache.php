<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductMatchCache extends Model
{
    protected $table = 'tbUrunEslestirmeOnbellek';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = ['ExcelUrunAdi', 'EslesenUrunNo', 'EslesenUrunTur', 'OlusturmaTarihi'];
}
