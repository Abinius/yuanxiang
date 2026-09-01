<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_id',
        'amount',
        'commission_amount',
        'farm_amount',
        'status',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }
}
