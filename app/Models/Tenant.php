<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'operator_org_id',
        'plan_id',
        'settings',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => TenantStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }
}
