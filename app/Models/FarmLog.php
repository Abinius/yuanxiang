<?php

namespace App\Models;

use App\Enums\FarmLogType;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FarmLog extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plot_id',
        'plant_id',
        'author_id',
        'type',
        'title',
        'content',
        'images',
        'video_url',
        'occurred_at',
        'fertilizer_batch_id',
        'is_trace_node',
        'is_public',
        'source',
        'payload',
        'lang',
    ];

    protected function casts(): array
    {
        return [
            'type' => FarmLogType::class,
            'images' => 'array',
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'is_trace_node' => 'boolean',
            'is_public' => 'boolean',
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

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function fertilizerBatch()
    {
        return $this->belongsTo(FertilizerBatch::class);
    }

    /** 溯源节点（is_trace_node=true）。 */
    public function scopeTraceNode(Builder $query): Builder
    {
        return $query->where('is_trace_node', true);
    }
}
