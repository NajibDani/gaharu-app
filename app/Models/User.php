<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $table = 'users';
    protected $fillable = ['nama', 'username', 'password', 'role_id', 'gudang_id']; // <--- Tambah gudang_id
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function gudang()
{
    return $this->belongsTo(MasterGudang::class, 'gudang_id');
}

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
    public function isSuperAdmin(): bool
    {
        $roleName = $this->role->nama ?? '';
        return in_array($roleName, ['Super Admin', 'Superadmin', 'Administrator']);
    }

    public function isGudang(): bool
    {
        $roleName = $this->role->nama ?? '';
        return in_array($roleName, ['Kepala Gudang', 'Gudang', 'Staff Gudang', 'Admin Gudang']);
    }

    public function canApprovePengeluaran(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $roleName = $this->role->nama ?? '';
        return in_array($roleName, ['Kepala Gudang', 'Gudang', 'Staff Gudang', 'Admin Gudang']);
    }
}
