<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TraceCode extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'code',
        'adoption_id',
        'harvest_id',
        'plot_id',
        'scanned_count',
        'chain_hash',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }

    public function harvest()
    {
        return $this->belongsTo(Harvest::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function incrementScans(): void
    {
        $this->increment('scanned_count');
    }
}
