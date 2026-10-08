<?php

namespace App\Http\Controllers\Pay;

use App\Exceptions\OrderCancelledWhilePaying;
use App\Enums\PaymentStatus;
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
                try {
                    $this->adoptions->markPaid($adoption, [
                        'transaction_id' => $data['transaction_id'],
                        'method' => 'wechat',
                    ]);
                } catch (OrderCancelledWhilePaying) {
                    $this->refundCancelledOrder($adoption, $data['transaction_id']);
                }
            }

            return $this->pay->notifySuccess();
        } catch (Throwable $e) {
            Log::warning('微信支付回调失败', ['error' => $e->getMessage()]);

            return response('FAIL', 500);
        }
    }

    /**
     * 竞态收口：订单在支付期间被弃付回收/退款，钱已到账 → 自动退款并把支付单记为已退。
     * 确定性 out_refund_no，微信重试回调不会二次退费。
     */
    private function refundCancelledOrder(Adoption $adoption, ?string $transactionId): void
    {
        $payment = $adoption->payments()
            ->where('status', PaymentStatus::Pending->value)
            ->latest('id')
            ->first();

        if (! $payment) {
            return;
        }

        $this->pay->requestRefund($adoption, '订单已过期取消，自动退款', null, 'RF-AD-'.$adoption->id, $payment);

        $payment->transitionTo(PaymentStatus::Refunded, [
            'refund_at' => now(),
            'transaction_id' => $transactionId ?? $payment->transaction_id,
        ]);
    }
}
