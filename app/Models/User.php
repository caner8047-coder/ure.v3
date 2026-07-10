<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Spatie\Permission\Traits\HasRoles;

#[Fillable(['personnel_no', 'name', 'surname', 'address', 'phone', 'email', 'password', 'department_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';

    /**
     * Get the user's department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the tasks assigned to the user.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user is an admin.
     * In V1, BolumAdiNo = 0 meant Admin. We can map that logic here.
     */
    public function isAdmin(): bool
    {
        $legacyDecision = $this->legacyPersonnelAdminDecision();
        if ($legacyDecision !== null) {
            return $legacyDecision;
        }

        return $this->department_id === null || (int) $this->department_id === 0;
    }

    private function legacyPersonnelAdminDecision(): ?bool
    {
        $email = trim((string) ($this->email ?? ''));
        if ($email === '') {
            return null;
        }

        try {
            if (!Schema::hasTable('tbPersonel')) {
                return null;
            }

            $row = DB::table('tbPersonel')
                ->select('BolumAdiNo')
                ->whereRaw('LOWER(TRIM(Mail)) = ?', [strtolower($email)])
                ->first();

            if (!$row) {
                return null;
            }

            return $row->BolumAdiNo !== null && (int) $row->BolumAdiNo === 0;
        } catch (\Throwable) {
            return null;
        }
    }
}
