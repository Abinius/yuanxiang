<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_id',
        'coupon_id',
        'amount_off',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }
}
