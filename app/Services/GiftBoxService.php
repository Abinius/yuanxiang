<?php

namespace App\Services;

use App\Enums\GiftBoxStatus;
use App\Models\Adoption;
use App\Models\GiftBox;
use App\Support\Code;
use Illuminate\Support\Facades\Storage;

/**
 * 3.3 节日礼盒：权益额度校验创建 → 定制（收礼人/寄语/亲笔签）→ 制作 → 发货 → 送达。
 * 亲笔签：Canvas toDataURL PNG → base64 解码 → Storage 落盘（gift-signatures/）。
 */
class GiftBoxService
{
    public function create(Adoption $adoption, string $festival, int $year): GiftBox
    {
        $quota = (int) data_get($adoption->plan?->festival_quota, $festival, 0);
        abort_if($quota <= 0, 422, '该节无可用礼盒额度');

        $used = GiftBox::query()
            ->where('adoption_id', $adoption->id)
            ->where('festival', $festival)
            ->where('year', $year)
            ->count();
        abort_if($used >= $quota, 422, '该节礼盒额度已用完');

        return GiftBox::create([
            'tenant_id' => $adoption->tenant_id,
            'adoption_id' => $adoption->id,
            'festival' => $festival,
            'year' => $year,
            'code' => $this->uniqueCode(),
            'status' => GiftBoxStatus::Draft->value,
        ]);
    }

    public function customize(GiftBox $giftBox, array $data, ?string $signatureBase64 = null): void
    {
        $giftBox->recipient_name = $data['recipient_name'] ?? null;
        $giftBox->recipient_phone = $data['recipient_phone'] ?? null;
        $giftBox->address_id = $data['address_id'] ?? null;
        $giftBox->message = $data['message'] ?? null;

        if ($signatureBase64) {
            $path = 'gift-signatures/'.$giftBox->code.'.png';
            Storage::disk('public')->put($path, $this->decodeSignature($signatureBase64));
            $giftBox->signature_image = $path;
        }

        $giftBox->save();
    }

    public function markMaking(GiftBox $giftBox): void
    {
        abort_unless($giftBox->status === GiftBoxStatus::Draft, 422, '仅草稿可开始制作');
        $giftBox->forceFill(['status' => GiftBoxStatus::Making->value])->save();
    }

    public function markShipped(GiftBox $giftBox, string $trackingNo, ?string $carrier = null): void
    {
        abort_unless(in_array($giftBox->status, [GiftBoxStatus::Draft, GiftBoxStatus::Making], true), 422, '当前状态不可发货');
        $giftBox->forceFill([
            'status' => GiftBoxStatus::Shipped->value,
            'tracking_no' => $trackingNo,
            'carrier' => $carrier,
            'shipped_at' => now(),
        ])->save();
    }

    public function markDelivered(GiftBox $giftBox): void
    {
        abort_unless($giftBox->status === GiftBoxStatus::Shipped, 422, '仅已发货可送达');
        $giftBox->forceFill([
            'status' => GiftBoxStatus::Delivered->value,
            'received_at' => now(),
        ])->save();
    }

    private function uniqueCode(): string
    {
        return Code::dated('GB', fn ($c) => GiftBox::where('code', $c)->exists(), 8, '礼盒码');
    }

    private function decodeSignature(string $dataUrl): string
    {
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $dataUrl);
        $png = base64_decode($base64);

        abort_if($png === false || strlen($png) > 2 * 1024 * 1024, 422, '签名图片无效或过大');

        return $png;
    }
}
