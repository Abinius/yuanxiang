<?php

namespace App\Http\Controllers\Family;

use App\Models\FertilizerBatch;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 家人端：录入有机肥批次（fertilizer_batches，NXLB 投入品信息）。
 * scope=fertilizer。录入的是投入品批次信息，非有机产品认证声明（cert_status=not_started）。
 */
class FertilizerBatchController extends Controller
{
    public function create(Request $request)
    {
        $this->assertScope($request, 'fertilizer');

        return view('family.fertilizer.create', compact());
    }

    public function store(Request $request)
    {
        $member = $this->assertScope($request, 'fertilizer');

        $data = $request->validate([
            'batch_no' => ['required', 'string', 'max:60', Rule::unique('fertilizer_batches', 'batch_no')->where('tenant_id', Tenant::current()->id)],
            'produced_at' => ['required', 'date'],
            'nxlb_ref' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'test_report_url' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = new FertilizerBatch();
        $batch->tenant_id = Tenant::current()->id;
        $batch->farm_id = $member->farm_id;
        $batch->batch_no = $data['batch_no'];
        $batch->produced_at = $data['produced_at'];
        $batch->nxlb_ref = $data['nxlb_ref'] ?? null;
        $batch->ingredients = $data['ingredients'] ?? null;
        $batch->test_report_url = $data['test_report_url'] ?? null;
        $batch->save();

        return redirect()->route('tenant.family.dashboard', [])
            ->with('ok', '有机肥批次已录入');
    }

    /** G8：编辑（复用 create 视图）。fertilizer scope 已限权；批次为共享投入品，tenant_admin 直改。 */
    public function edit(FertilizerBatch $batch, Request $request)
    {
        $this->assertScope($request, 'fertilizer');

        return view('family.fertilizer.create', compact('batch'));
    }

    public function update(FertilizerBatch $batch, Request $request)
    {
        $this->assertScope($request, 'fertilizer');

        $data = $request->validate([
            'batch_no' => ['required', 'string', 'max:60', Rule::unique('fertilizer_batches', 'batch_no')->where('tenant_id', Tenant::current()->id)->ignore($batch->id)],
            'produced_at' => ['required', 'date'],
            'nxlb_ref' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'test_report_url' => ['nullable', 'string', 'max:500'],
        ]);

        $batch->update($data);

        return redirect()->route('tenant.family.dashboard', [])
            ->with('ok', '已更新');
    }
}
