<?php

namespace App\Services;

use App\Enums\FarmLogType;
use App\Models\DetectionReport;
use App\Models\FarmLog;
use App\Models\Harvest;
use App\Models\Plot;

/**
 * 溯源节点聚合：三路查询（farm_logs / harvests / detection_reports）
 * → 统一节点数组按时间升序。TraceController 与 ScanController 共用。
 * 采收协调：harvests 表为权威结构化节点，同日同 plot 的 farm_log(harvest)
 * 叙事并入（键 plot_id|date），无 harvests 行时降级为独立采收记录节点。
 * 合规：cert_status=not_started，只写「有机肥（NXLB）投入品」，无认证宣称。
 */
class TraceService
{
    public function nodesForPlot(Plot $plot): array
    {
        $plotIds = $plot->relatedPlotIds();

        // 公开页双保险：私密记录（is_public=false）不上更公开的溯源页
        $logs = FarmLog::query()
            ->traceNode()
            ->where('is_public', true)
            ->whereIn('plot_id', $plotIds)
            ->with(['author', 'fertilizerBatch', 'plot:id,code'])
            ->get();

        $harvests = Harvest::query()
            ->whereIn('plot_id', $plotIds)
            ->with('plot:id,code')
            ->get();

        $reports = DetectionReport::query()
            ->whereIn('plot_id', $plotIds)
            ->with('plot:id,code')
            ->get();

        $nodes = $this->buildNodes($logs, $harvests, $reports);
        usort($nodes, fn ($a, $b) => $a['sort_at']->getTimestamp() <=> $b['sort_at']->getTimestamp());

        return $nodes;
    }

    /** 合并三路数据为统一溯源节点数组。 */
    private function buildNodes($logs, $harvests, $reports): array
    {
        $nodes = [];

        // 1) harvests 表 → 权威结构化采收节点，键 = plot_id|date
        $harvestIndex = [];
        foreach ($harvests as $h) {
            $key = $h->plot_id.'|'.$h->harvested_at->toDateString();
            $harvestIndex[$key] = count($nodes);
            $nodes[] = [
                'kind' => 'harvest',
                'badge' => '采收',
                'title' => $h->season_year.' 年度采收',
                'content' => $h->notes ?? '',
                'images' => [],
                'date' => $h->harvested_at->toDateString(),
                'sort_at' => $h->harvested_at,
                'plot_code' => $h->plot?->code,
                'author' => null,
                'extra' => [
                    'dry_weight_kg' => $h->dry_weight_kg,
                    'quality_grade' => $h->quality_grade,
                    'note_title' => null,
                    'note' => null,
                ],
            ];
        }

        // 2) farm_logs 溯源节点（采收叙事并入同键结构化节点）
        foreach ($logs as $log) {
            $date = $log->occurred_at?->toDateString() ?? now()->toDateString();
            $key = $log->plot_id.'|'.$date;

            if ($log->type === FarmLogType::Harvest && isset($harvestIndex[$key])) {
                $i = $harvestIndex[$key];
                $nodes[$i]['extra']['note_title'] = $log->title;
                $nodes[$i]['extra']['note'] = $log->content ?? '';
                $nodes[$i]['images'] = array_merge($nodes[$i]['images'], $log->images ?? []);
                continue;
            }

            [$kind, $badge] = match ($log->type) {
                FarmLogType::Fertilize => ['fertilize', '施肥'],
                FarmLogType::Harvest => ['harvest_note', '采收记录'],
                default => ['inspect', $log->type->label()],
            };

            $nodes[] = [
                'kind' => $kind,
                'badge' => $badge,
                'title' => $log->title,
                'content' => $log->content ?? '',
                'images' => $log->images ?? [],
                'date' => $date,
                'sort_at' => $log->occurred_at ?? now(),
                'plot_code' => $log->plot?->code,
                'author' => $log->author?->nickname,
                'extra' => $log->type === FarmLogType::Fertilize && $log->fertilizerBatch
                    ? ['batch' => $this->batchCard($log->fertilizerBatch)]
                    : [],
            ];
        }

        // 3) 检测报告（结构化）
        foreach ($reports as $r) {
            $nodes[] = [
                'kind' => 'detection',
                'badge' => '检测报告',
                'title' => $r->report_no,
                'content' => '',
                'images' => [],
                'date' => $r->issued_at?->toDateString() ?? '',
                'sort_at' => $r->issued_at ?? now(),
                'plot_code' => $r->plot?->code,
                'author' => null,
                'extra' => [
                    'type' => $r->type,
                    'org' => $r->org,
                    'qualified' => $r->qualified,
                    'result_summary' => $r->result_summary ?? [],
                    'report_url' => $r->report_url,
                    'batch_ref' => $r->batch_ref,
                ],
            ];
        }

        return $nodes;
    }

    /** NXLB 批次卡（批次号/成分/检测链接）。 */
    private function batchCard($batch): array
    {
        return [
            'batch_no' => $batch->batch_no,
            'produced_at' => $batch->produced_at?->toDateString(),
            'nxlb_ref' => $batch->nxlb_ref,
            'ingredients' => $batch->ingredients,
            'test_report_url' => $batch->test_report_url,
        ];
    }
}
