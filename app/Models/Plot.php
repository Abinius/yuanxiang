<?php

namespace App\Models;

use App\Enums\PlotStatus;
use App\Enums\PlotType;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plot extends Model
{
    use SoftDeletes, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'farm_id',
        'plan_id',
        'parent_plot_id',
        'type',
        'code',
        'mu_area',
        'price_yearly',
        'story',
        'status',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'type' => PlotType::class,
            'status' => PlotStatus::class,
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_plot_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_plot_id');
    }

    public function cameras()
    {
        return $this->hasMany(Camera::class);
    }

    public function farmLogs()
    {
        return $this->hasMany(FarmLog::class);
    }

    public function harvests()
    {
        return $this->hasMany(Harvest::class);
    }

    public function adoptions()
    {
        return $this->morphMany(Adoption::class, 'adoptable');
    }

    /** 溯源/动态范围：分地→自身；拼团田→组+其下株；株→株+父组。 */
    public function relatedPlotIds(): array
    {
        return match ($this->type) {
            PlotType::Group => array_merge(
                [$this->id],
                $this->children()->pluck('id')->all(),
            ),
            PlotType::Plant => array_filter([$this->id, $this->parent_plot_id], fn ($v) => $v !== null),
            PlotType::Plot => [$this->id],
        };
    }

    public function detectionReports()
    {
        return $this->hasMany(DetectionReport::class);
    }

    public function traceCodes()
    {
        return $this->hasMany(TraceCode::class);
    }
}
