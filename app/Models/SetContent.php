<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SetContent extends Model
{
    protected $table = 'tbSetIcerikleri';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = ['SetNo', 'UrunNo', 'Adet'];

    public function setDefinition()
    {
        return $this->belongsTo(SetDefinition::class, 'SetNo', 'No');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'UrunNo', 'No');
    }
}
