<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'operator_org_id',
        'name',
        'owner_name',
        'owner_user_id',
        'region',
        'country',
        'cert_status',
        'cert_expires_at',
        'cert_doc_url',
        'settle_bank',
        'settle_account',
        'export_qualifications',
    ];

    protected function casts(): array
    {
        return [
            'cert_expires_at' => 'datetime',
            'export_qualifications' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function operatorOrg()
    {
        return $this->belongsTo(Organization::class, 'operator_org_id');
    }

    public function plots()
    {
        return $this->hasMany(Plot::class);
    }

    public function farmMembers()
    {
        return $this->hasMany(FarmMember::class);
    }

    public function cameras()
    {
        return $this->hasMany(Camera::class);
    }

    public function harvests()
    {
        return $this->hasMany(Harvest::class);
    }

    public function farmLogs()
    {
        return $this->hasMany(FarmLog::class);
    }
}
