<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'name',
        'subject_type',
        'price_yearly',
        'delivery_rule',
        'benefits',
        'festival_quota',
        'stock_mode',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'delivery_rule' => 'array',
            'benefits' => 'array',
            'festival_quota' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plots()
    {
        return $this->hasMany(Plot::class);
    }

    public function adoptions()
    {
        return $this->hasMany(Adoption::class);
    }
}
