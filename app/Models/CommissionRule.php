<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plan_id',
        'commission_rate',
        'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
