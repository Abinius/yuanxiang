<?php

namespace App\Models;

use App\Enums\GiftBoxStatus;
use App\Enums\GiftFestival;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftBox extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_id',
        'festival',
        'year',
        'code',
        'recipient_name',
        'recipient_phone',
        'address_id',
        'signature_image',
        'message',
        'status',
        'tracking_no',
        'carrier',
        'shipped_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'festival' => GiftFestival::class,
            'status' => GiftBoxStatus::class,
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
