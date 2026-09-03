<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'phone',
        'username',
        'nickname',
        'avatar',
        'real_name',
        'email',
        'password',
        'openid',
        'unionid',
        'role',
        'is_disabled',
        'village_card_no',
        'joined_year',
        'password_set_at',
        'member_level',
        'member_since',
        'birthday',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'password_set_at' => 'datetime',
            'member_since' => 'datetime',
            'birthday' => 'date',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adoptions()
    {
        return $this->hasMany(Adoption::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function farmMemberships()
    {
        return $this->hasMany(FarmMember::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === UserRole::PlatformAdmin;
    }
}
