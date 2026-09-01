<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'operator_org_id',
        'plan_id',
        'settings',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => TenantStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function operatorOrg()
    {
        return $this->belongsTo(Organization::class, 'operator_org_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function adoptions()
    {
        return $this->hasMany(Adoption::class);
    }

    /** 读取租户设置（回退平台默认）。 */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, data_get(config('site.defaults'), $key, $default));
    }

    /** 平台默认 + 租户覆盖合并后的设置。 */
    public function defaultSettings(): array
    {
        return array_merge(config('site.defaults'), $this->settings ?? []);
    }

    /** SEO 元数据（页面可再覆盖）。 */
    public function seo(array $overrides = []): array
    {
        return \App\Services\SeoService::fromTenant($this, $overrides);
    }
}
