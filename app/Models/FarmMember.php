<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FarmMember extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'farm_id',
        'relation',
        'permission_scope',
    ];

    protected function casts(): array
    {
        return [
            'permission_scope' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
