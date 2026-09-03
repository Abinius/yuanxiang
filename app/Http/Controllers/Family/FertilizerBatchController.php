<?php

namespace App\Http\Controllers\Family;

use App\Models\FertilizerBatch;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 家人端：录入有机肥批次（fertilizer_batches，NXLB 投入品信息）。
 * scope=fertilizer。录入的是投入品批次信息，非有机产品认证声明（cert_status=not_started）。
 */
class FertilizerBatchController extends Controller
{
    public function create(Tenant $tenant, Request $request)
    {
        $this->assertScope($request, 'fertilizer');

        return view('family.fertilizer.create', compact('tenant'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'fertilizer');

        $data = $request->validate([
            'batch_no' => ['required', 'string', 'max:60', Rule::unique('fertilizer_batches', 'batch_no')->where('tenant_id', $tenant->id)],
            'produced_at' => ['required', 'date'],
            'nxlb_ref' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'test_report_url' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = new FertilizerBatch();
        $batch->tenant_id = $tenant->id;
        $batch->farm_id = $member->farm_id;
        $batch->batch_no = $data['batch_no'];
        $batch->produced_at = $data['produced_at'];
        $batch->nxlb_ref = $data['nxlb_ref'] ?? null;
        $batch->ingredients = $data['ingredients'] ?? null;
        $batch->test_report_url = $data['test_report_url'] ?? null;
        $batch->save();

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '有机肥批次已录入');
    }

    /** G8：编辑（复用 create 视图）。fertilizer scope 已限权；批次为共享投入品，tenant_admin 直改。 */
    public function edit(Tenant $tenant, FertilizerBatch $batch, Request $request)
    {
        $this->assertScope($request, 'fertilizer');
        abort_if($batch->tenant_id !== $tenant->id, 404);

        return view('family.fertilizer.create', compact('tenant', 'batch'));
    }

    public function update(Tenant $tenant, FertilizerBatch $batch, Request $request)
    {
        $this->assertScope($request, 'fertilizer');
        abort_if($batch->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'batch_no' => ['required', 'string', 'max:60', Rule::unique('fertilizer_batches', 'batch_no')->where('tenant_id', $tenant->id)->ignore($batch->id)],
            'produced_at' => ['required', 'date'],
            'nxlb_ref' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'test_report_url' => ['nullable', 'string', 'max:500'],
        ]);

        $batch->update($data);

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '已更新');
    }
}
