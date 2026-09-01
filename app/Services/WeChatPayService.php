<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Adoption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yansongda\LaravelPay\Facades\Pay;

/**
 * 微信 JSAPI 支付封装：下单(mp) → 回调验签解密 → 退款。
 *
 * 只负责与微信支付接口打交道（凭证经 config/pay.php 从 env 注入，主体=花乌巷食品）；
 * 状态机写入交给 AdoptionService::markPaid/markRefunded，保证重复回调幂等无副作用。
 */
class WeChatPayService
{
    /**
     * 下单并返回 JSSDK invoke 参数（appId/timeStamp/nonceStr/package/signType/paySign）。
     */
    public function jsapi(Adoption $adoption, string $openid): array
    {
        $order = [
            'out_trade_no' => $adoption->adoption_no,
            'description' => '光彩云村庄·认养 '.$adoption->adoption_no,
            'amount' => [
                'total' => (int) bcmul((string) $adoption->annual_fee, '100', 0),
                'currency' => 'CNY',
            ],
            'payer' => ['openid' => $openid],
            'attach' => (string) $adoption->id,
        ];

        return Pay::wechat()->mp($order)->all();
    }

    /**
     * 验签 + 解密回调体，返回商户单号（= adoption_no）与微信交易号。
     */
    public function parseNotify(Request $request): array
    {
        $result = Pay::wechat()->callback([
            'body' => $request->getContent(),
            'headers' => $request->headers->all(),
        ]);

        $plain = $result->all();
        $resource = $plain['resource'] ?? [];

        return [
            'out_trade_no' => $plain['out_trade_no'] ?? $resource['out_trade_no'] ?? '',
            'transaction_id' => $plain['transaction_id'] ?? $resource['transaction_id'] ?? '',
        ];
    }

    /**
     * 支付回调成功应答（微信要求 {"code":"SUCCESS","message":"成功"}，否则重试）。
     */
    public function notifySuccess(): JsonResponse
    {
        return response()->json(['code' => 'SUCCESS', 'message' => '成功'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 向微信发起退款（仅接口调用；DB 状态由 AdoptionService::markRefunded 落库）。
     * $amount 为空 = 全额退；3.2 欠收折算退费用部分金额。
     * $outRefundNo 为空 = 随机单号；补退传确定性单号（'RF-<adjustment_id>'）使微信侧按 out_refund_no 幂等去重，防重试二次退费。
     */
    public function requestRefund(Adoption $adoption, string $reason = '认养取消', ?float $amount = null, ?string $outRefundNo = null): void
    {
        $payment = $adoption->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->latest('id')
            ->first();

        abort_unless($payment, 422, '无已支付订单可退款');

        // dev mock：无商户凭证时模拟退款成功（DB 状态由 AdoptionService 落库）
        if (config('wechat.mock')) {
            return;
        }

        $refundCents = (int) bcmul((string) ($amount ?? $payment->amount), '100', 0);
        $totalCents = (int) bcmul((string) $payment->amount, '100', 0);

        Pay::wechat()->refund([
            'out_trade_no' => $adoption->adoption_no,
            'out_refund_no' => $outRefundNo ?? 'RF'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'reason' => $reason,
            'amount' => [
                'refund' => $refundCents,
                'total' => $totalCents,
                'currency' => 'CNY',
            ],
        ]);
    }
}
