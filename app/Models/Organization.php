<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'role',
        'tax_no',
        'bank',
        'wx_mch_id',
        'food_license_no',
        'license_scope',
        'status',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'operator_org_id');
    }
}
