<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'price_yearly',
        'limits',
        'commission_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
        ];
    }
}
