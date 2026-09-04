<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M3 认养合同：签约时由 ContractService 生成，条款按当时版本快照锁定。
 */
class Contract extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'adoption_id',
        'contract_no',
        'template_version',
        'clauses',
        'signed_at',
        'signed_ip',
        'pdf_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'clauses' => 'array',
            'signed_at' => 'datetime',
        ];
    }
}
