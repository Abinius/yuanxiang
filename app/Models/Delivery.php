<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_id',
        'harvest_id',
        'address_id',
        'spec',
        'tracking_no',
        'carrier',
        'status',
        'shipped_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'status' => DeliveryStatus::class,
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }

    public function harvest()
    {
        return $this->belongsTo(Harvest::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
