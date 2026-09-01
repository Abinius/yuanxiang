<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'rule',
        'starts_at',
        'ends_at',
        'stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rule' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }
}
