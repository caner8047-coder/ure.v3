<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SetDefinition extends Model
{
    protected $table = 'tbSetTanimlari';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = ['ExcelSetAdi', 'SetAdi', 'OlusturmaTarihi', 'Aktif'];

    public function contents()
    {
        return $this->hasMany(SetContent::class, 'SetNo', 'No');
    }
}
