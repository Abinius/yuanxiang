<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\View\View;

/**
 * 公示协议（认养服务协议 / 隐私政策）：公开可读，文案集中在 config/site.php，法务定稿只改配置。
 * 单笔认养协议的条款快照由 ContractService 生成，不在本页。
 */
class AgreementController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function show(string $key): View
    {
        $items = $this->settings->agreements()['items'] ?? [];
        abort_unless(array_key_exists($key, $items), 404);

        return view('site.agreement.show', [
            'agreements' => $this->settings->agreements(),
            'key' => $key,
        ]);
    }
}
