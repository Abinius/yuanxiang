<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetectionReport extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plot_id',
        'report_no',
        'type',
        'batch_ref',
        'org',
        'report_url',
        'result_summary',
        'qualified',
        'issued_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'result_summary' => 'array',
            'qualified' => 'boolean',
            'issued_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }
}
