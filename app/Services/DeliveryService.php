<?php

namespace App\Services;

use App\Enums\AdoptionStatus;
use App\Enums\DeliveryStatus;
use App\Models\Adoption;
use App\Models\Delivery;
use App\Models\Harvest;
use App\Models\Plot;
use Illuminate\Support\Facades\DB;

/**
 * 3.1 配送链路：按采收打单（为 plot 的 active 认养人建 pending 配送单）→ 发货 → 签收。
 * 地址取认养人 is_default 或最新一条（下单已建 addresses，但未挂认养单）。
 * adoption↔harvest 经 deliveries.adoption_id + harvest_id 显式桥接。
 */
class DeliveryService
{
    public function __construct(
        private readonly TraceCodeService $traceCodes,
    ) {}

    /** 为一次采收生成配送单（跳过已生成的认养单）+ 每箱一溯源码。@return Delivery[] */
    public function createForHarvest(Harvest $harvest): array
    {
        $adoptions = Adoption::query()
            ->where('adoptable_type', Plot::class)
            ->where('adoptable_id', $harvest->plot_id)
            ->where('status', AdoptionStatus::Active)
            ->get();

        return DB::transaction(function () use ($harvest, $adoptions) {
            $created = [];
            foreach ($adoptions as $adoption) {
                if (Delivery::query()
                    ->where('adoption_id', $adoption->id)
                    ->where('harvest_id', $harvest->id)
                    ->exists()
                ) {
                    continue;
                }

                // 配送单与溯源码必须原子写入：只打单不生成码会留下孤儿配送单
                $delivery = Delivery::create([
                    'tenant_id' => $harvest->tenant_id,
                    'adoption_id' => $adoption->id,
                    'harvest_id' => $harvest->id,
                    'address_id' => $this->pickAddressId($adoption),
                    'spec' => ['packing' => '保底分装'],
                    'status' => DeliveryStatus::Pending->value,
                ]);

                // 唯一约束 (adoption_id, harvest_id) 兜住并发双击
                $this->traceCodes->generate($harvest, 1, $adoption->id);
                $created[] = $delivery;
            }

            return $created;
        });
    }

    public function markShipped(Delivery $delivery, string $trackingNo, ?string $carrier = null): void
    {
        $delivery->transitionTo(DeliveryStatus::Shipped, [
            'tracking_no' => $trackingNo,
            'carrier' => $carrier,
            'shipped_at' => now(),
        ]);
    }

    public function markReceived(Delivery $delivery): void
    {
        $delivery->transitionTo(DeliveryStatus::Delivered, ['received_at' => now()]);
    }

    private function pickAddressId(Adoption $adoption): ?int
    {
        $address = $adoption->user?->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        return $address?->id;
    }
}
