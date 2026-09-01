<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Camera extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plot_id',
        'name',
        'device_no',
        'provider',
        'stream_url',
        'playback_url',
        'token',
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

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }
}
