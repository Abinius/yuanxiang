<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use Illuminate\Http\Request;

/**
 * M3 认养合同查看（云乡民本人）：HTML 可打印视图（浏览器另存 PDF）。
 */
class ContractController extends Controller
{
    public function show(Request $request, Adoption $adoption)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);

        $contract = $adoption->contract;
        abort_if(! $contract, 404, '合同尚未生成');

        return view('site.contract.show', compact('adoption', 'contract'));
    }
}
