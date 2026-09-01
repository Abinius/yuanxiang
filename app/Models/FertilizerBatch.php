<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FertilizerBatch extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'batch_no',
        'produced_at',
        'nxlb_ref',
        'ingredients',
        'test_report_url',
    ];

    protected function casts(): array
    {
        return [
            'produced_at' => 'date',
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

    public function farmLogs()
    {
        return $this->hasMany(FarmLog::class);
    }
}
