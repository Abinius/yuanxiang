<?php

namespace App\Support;

use App\Enums\PlotType;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 田地录入校验的单一来源：后台（可指定基地）与家人端（基地锁定）共用同一份规则，
 * 避免两处逐字重复后各自漂移。
 */
final class PlotRules
{
    /**
     * @param  int|null  $farmId  家人端传本基地 id（farm_id 不进表单，由控制器强制写入）；null = 后台可指定
     * @param  array  $readonly  家人端不能改的字段（定价与上下架状态属经营决策，归后台）
     */
    public static function rules(Tenant $tenant, Request $request, ?Plot $plot = null, ?int $farmId = null, array $readonly = []): array
    {
        $rules = [
            'plan_id' => ['nullable', Rule::exists('plans', 'id')->where('tenant_id', $tenant->id)],
            'parent_plot_id' => [
                'nullable',
                Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)->where('type', PlotType::Group->value),
                Rule::requiredIf($request->input('type') === PlotType::Plant->value),
            ],
            'type' => ['required', Rule::enum(PlotType::class)],
            'code' => ['required', 'string', 'max:40', Rule::unique('plots', 'code')
                ->where('tenant_id', $tenant->id)->ignore($plot?->id)],
            'mu_area' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['available', 'adopted', 'sold_out', 'offline'])],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'story' => ['nullable', 'string', 'max:1000'],
        ];

        if ($farmId === null) {
            $rules = ['farm_id' => ['required', Rule::exists('farms', 'id')->where('tenant_id', $tenant->id)]] + $rules;
        }

        return array_diff_key($rules, array_flip($readonly));
    }
}
