<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Harvest extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plot_id',
        'season_year',
        'harvested_at',
        'dry_weight_kg',
        'quality_grade',
        'handler_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'harvested_at' => 'date',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }
}
