<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role', 'is_default')
            ->withTimestamps();
    }

    public function hasCompanyRole(string $companyId, string $role): bool
    {
        return $this->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('role', $role)
            ->exists();
    }

    public function isAdminOf(string $companyId): bool
    {
        return $this->hasCompanyRole($companyId, 'admin');
    }

    public function getDefaultCompany(): ?Company
    {
        return $this->companies()->wherePivot('is_default', true)->first();
    }
}
