<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'author_id',
        'title',
        'content',
        'type',
        'images',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
