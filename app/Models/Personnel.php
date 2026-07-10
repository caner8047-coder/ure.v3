<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Personnel extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'tbPersonel';
    protected $primaryKey = 'PersonelNo';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'PersonelNo',
        'Ad',
        'Soyad',
        'Telefon',
        'Adres',
        'Mail',
        'Sifre',
        'BolumAdiNo',
    ];

    protected $hidden = ['Sifre'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'BolumAdiNo', 'No');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'PersonelNo', 'PersonelNo');
    }

    public function getAuthPassword()
    {
        return $this->Sifre ?? '';
    }

    public function getNameAttribute(): string
    {
        return trim((string) ($this->Ad ?? ''));
    }

    public function getSurnameAttribute(): string
    {
        return trim((string) ($this->Soyad ?? ''));
    }

    public function getEmailAttribute(): string
    {
        return trim((string) ($this->Mail ?? ''));
    }

    public function getPhoneAttribute(): string
    {
        return trim((string) ($this->Telefon ?? ''));
    }

    public function getAddressAttribute(): string
    {
        return trim((string) ($this->Adres ?? ''));
    }

    public function getPersonnelNoAttribute(): int
    {
        return (int) ($this->PersonelNo ?? 0);
    }

    public function getDepartmentIdAttribute(): ?int
    {
        $departmentId = (int) ($this->BolumAdiNo ?? 0);

        return $departmentId > 0 ? $departmentId : null;
    }

    public function getPasswordAttribute(): ?string
    {
        return $this->Sifre;
    }

    public function isAdmin(): bool
    {
        return $this->BolumAdiNo !== null && (int) $this->BolumAdiNo === 0;
    }
}
