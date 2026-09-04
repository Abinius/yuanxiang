<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * M4 佣金流水：推荐人按 tier 比例分得，经冷却期转正、提现扣减。
 */
class CommissionLedger extends Model
{
    use TenantScoped;

    protected $table = 'commission_ledger';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'adoption_id',
        'payout_id',
        'tier',
        'rate',
        'amount',
        'status',
        'settled_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }
}
