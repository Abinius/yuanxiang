<?php

namespace App\Models;

use App\Enums\AdoptionStatus;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Adoption extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_no',
        'user_id',
        'adoptable_type',
        'adoptable_id',
        'plan_id',
        'farm_id',
        'season_year',
        'annual_fee',
        'start_date',
        'end_date',
        'named_label',
        'agreement_signed_at',
        'auto_renew',
        'transferred_from_id',
        'upgraded_from_id',
        'renewed_from_id',
        'status',
        'chain_hash',
        'tx_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdoptionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'agreement_signed_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adoptable()
    {
        return $this->morphTo();
    }

    public function contract()
    {
        return $this->hasOne(\App\Models\Contract::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function giftBoxes()
    {
        return $this->hasMany(GiftBox::class);
    }

    public function adjustments()
    {
        return $this->hasMany(AdoptionAdjustment::class);
    }

    public function traceCodes()
    {
        return $this->hasMany(TraceCode::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    /** F9：距离到期剩余天数（负数=已过到期日；无 end_date 返回 null）。 */
    public function daysRemaining(): ?int
    {
        return $this->end_date ? now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false) : null;
    }
}
