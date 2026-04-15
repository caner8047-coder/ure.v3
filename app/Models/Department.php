<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'tbBolum';
    protected $primaryKey = 'No';
    public $timestamps = false;

    protected $fillable = ['BolumAdi'];

    public function users()
    {
        return $this->hasMany(Personnel::class, 'BolumAdiNo', 'No');
    }

    public function personnel()
    {
        return $this->hasMany(Personnel::class, 'BolumAdiNo', 'No');
    }

    public function stocks()
    {
        return $this->hasMany(DepartmentStock::class, 'BolumAdiNo', 'No');
    }

    public function pools()
    {
        return $this->hasMany(ProductionPool::class, 'BolumAdiNo', 'No');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'BolumAdiNo', 'No');
    }
}
