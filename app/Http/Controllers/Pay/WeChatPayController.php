<?php

namespace App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Services\AdoptionService;
use App\Services\WeChatPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 微信支付回调：无租户中间件，按全局唯一 adoption_no 定位订单落库。
 * 单租户下无需切换上下文。
 */
class WeChatPayController extends Controller
{
    public function __construct(
        private readonly WeChatPayService $pay,
        private readonly AdoptionService $adoptions,
    ) {
    }

    public function notify(Request $request)
    {
        try {
            $data = $this->pay->parseNotify($request);

            $adoption = Adoption::query()
                ->where('adoption_no', $data['out_trade_no'])
                ->first();

            if ($adoption) {
                $this->adoptions->markPaid($adoption, [
                    'transaction_id' => $data['transaction_id'],
                    'method' => 'wechat',
                ]);
            }

            return $this->pay->notifySuccess();
        } catch (Throwable $e) {
            Log::warning('微信支付回调失败', ['error' => $e->getMessage()]);

            return response('FAIL', 500);
        }
    }
}
