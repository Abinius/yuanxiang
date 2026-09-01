<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'payable_type',
        'payable_id',
        'amount',
        'method',
        'transaction_id',
        'status',
        'paid_at',
        'refund_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'refund_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payable()
    {
        return $this->morphTo();
    }
}
