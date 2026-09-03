<?php

use App\Http\Controllers\Admin\AdjustmentController as AdminAdjustments;
use App\Http\Controllers\Admin\AdoptionController as AdminAdoptions;
use App\Http\Controllers\Admin\CameraController as AdminCameras;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\DeliveryController as AdminDeliveries;
use App\Http\Controllers\Admin\FarmLogController as AdminFarmLogs;
use App\Http\Controllers\Admin\GiftBoxController as AdminGiftBoxes;
use App\Http\Controllers\Admin\ShipmentWorkbenchController as AdminShipments;
use App\Http\Controllers\Admin\PromotionController as AdminPromotions;
use App\Http\Controllers\Admin\PlotController as AdminPlots;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use App\Http\Controllers\Admin\ShortLinkController as AdminShortLinks;
use App\Http\Controllers\Admin\TraceCodeController as AdminTraceCodes;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Family\DashboardController as FamilyDashboard;
use App\Http\Controllers\Family\FarmLogController as FamilyLogs;
use App\Http\Controllers\Family\FertilizerBatchController as FamilyFertilizer;
use App\Http\Controllers\Family\HarvestController as FamilyHarvest;
use App\Http\Controllers\Family\PlotController as FamilyPlots;
use App\Http\Controllers\Pay\WeChatPayController;
use App\Http\Controllers\Site\AdoptController;
use App\Http\Controllers\Site\GiftBoxController;
use App\Http\Controllers\Site\GiftScanController;
use App\Http\Controllers\Site\LiveController;
use App\Http\Controllers\Site\MyPlotController;
use App\Http\Controllers\Site\ReferralController;
use App\Http\Controllers\Site\ScanController;
use App\Http\Controllers\Site\ShareController;
use App\Http\Controllers\Site\ShortLinkController;
use App\Http\Controllers\Site\TraceController;
use Illuminate\Support\Facades\Route;

// 平台公共：选店入口
Route::get('/', function () {
    return view('welcome');
});

// 微信支付回调（微信服务器推送：无租户上下文、免 CSRF/登录）
Route::post('/pay/wechat/notify', [WeChatPayController::class, 'notify'])->name('pay.wechat.notify');

// 站点 SEO：robots + sitemap（全局）
Route::get('/robots.txt', function () {
    return response(
        "User-agent: *\nAllow: /\nDisallow: /my\nDisallow: /live\nDisallow: /admin\nDisallow: /family\nDisallow: /u\n",
        200,
        ['Content-Type' => 'text/plain']
    );
});
Route::get('/sitemap.xml', function () {
    $urls = [];
    foreach (\App\Models\Tenant::where('status', 'active')->get() as $t) {
        $urls[] = url('/t/'.$t->slug);
        $urls[] = url('/t/'.$t->slug.'/adopt');
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) {
        $xml .= "\n  <url><loc>".htmlspecialchars($u, ENT_XML1).'</loc></url>';
    }
    $xml .= "\n</urlset>";

    return response($xml, 200, ['Content-Type' => 'application/xml']);
});

// 租户前台（SaaS 租户空间）：auth-only 资源路由加 tenant.member 防跨租户越权；公开页不加（保持跨租户可分享）
Route::prefix('t/{tenant:slug}')->middleware('tenant')->group(function () {
    Route::get('/', function (\Illuminate\Http\Request $request) {
        return view('site.home', [
            'tenant' => $request->attributes->get('tenant'),
            'user' => $request->user(),
        ]);
    })->name('tenant.home');

    // 双登录
    Route::get('/login', [LoginController::class, 'show'])->name('tenant.login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('tenant.login.post');
    Route::get('/login/wechat', [LoginController::class, 'wechat'])->name('tenant.login.wechat');
    Route::get('/login/wechat/callback', [LoginController::class, 'wechatCallback'])->name('tenant.wechat.callback');
    Route::post('/login/bind-phone', [LoginController::class, 'bindPhone'])->middleware(['auth', 'throttle:3,1'])->name('tenant.bind-phone');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('tenant.logout');

    // 认养
    Route::get('/adopt', [AdoptController::class, 'index'])->name('tenant.adopt.index');
    Route::get('/adopt/{plot}', [AdoptController::class, 'show'])->name('tenant.adopt.show');
    Route::post('/adopt/{plot}/order', [AdoptController::class, 'order'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.order');
    Route::get('/adopt/order/{adoption}/pay', [AdoptController::class, 'pay'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.pay');
    Route::post('/adopt/order/{adoption}/pay', [AdoptController::class, 'confirmPay'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.confirm-pay');
    Route::post('/adopt/order/{adoption}/wechat-pay', [AdoptController::class, 'wechatPay'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.wechat-pay');
    Route::post('/adopt/order/{adoption}/sign', [AdoptController::class, 'signAgreement'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.sign');
    Route::get('/adopt/order/{adoption}/success', [AdoptController::class, 'success'])->middleware(['auth', 'tenant.member'])->name('tenant.adopt.success');

    // 溯源时间线（公开，信任卖点，认养前转化入口）
    Route::get('/trace/{plot}', [TraceController::class, 'show'])->name('tenant.trace.show');

    // 溯源码扫码页（公开；码在租户内过滤，跨租户码自动 404）
    Route::get('/s/{code}', [ScanController::class, 'show'])->middleware('throttle:30,1')->name('tenant.scan.show');

    // 礼盒收礼人落地页（公开，拉新）
    Route::get('/gift/{code}', [GiftScanController::class, 'show'])->middleware('throttle:30,1')->name('tenant.gift.scan');

    // 短链接跳转（公开）+ 公开铭牌落地页（外链可打开）
    Route::get('/u/{code}', [ShortLinkController::class, 'redirect'])->middleware('throttle:60,1')->name('tenant.short-link.redirect');
    Route::get('/nameplate/{adoption}', [ShareController::class, 'nameplate'])->name('tenant.share.nameplate');

    // 我的田（云乡民：铭牌 + 生长日历 + 农事动态 + 分享）
    Route::get('/my', [MyPlotController::class, 'index'])->middleware(['auth', 'tenant.member'])->name('tenant.my.index');
    Route::get('/my/referral', [ReferralController::class, 'index'])->middleware(['auth', 'tenant.member'])->name('tenant.my.referral');
    Route::post('/my/plot/{adoption}/renew', [MyPlotController::class, 'renew'])->middleware(['auth', 'tenant.member'])->name('tenant.my.renew');
    Route::post('/my/plot/{adoption}/auto-renew', [MyPlotController::class, 'autoRenew'])->middleware(['auth', 'tenant.member'])->name('tenant.my.auto-renew');
    Route::get('/my/plot/{adoption}', [MyPlotController::class, 'show'])->middleware(['auth', 'tenant.member'])->name('tenant.my.plot');
    Route::get('/my/plot/{adoption}/nameplate', [MyPlotController::class, 'nameplate'])->middleware(['auth', 'tenant.member'])->name('tenant.my.nameplate');
    Route::post('/my/plot/{adoption}/deliveries/{delivery}/receive', [MyPlotController::class, 'receive'])->middleware(['auth', 'tenant.member'])->name('tenant.my.delivery.receive');

    // 节日礼盒（权益抵扣定制，owner-gated）
    Route::get('/my/plot/{adoption}/gifts', [GiftBoxController::class, 'index'])->middleware(['auth', 'tenant.member'])->name('tenant.my.gift.index');
    Route::get('/my/plot/{adoption}/gifts/create', [GiftBoxController::class, 'create'])->middleware(['auth', 'tenant.member'])->name('tenant.my.gift.create');
    Route::post('/my/plot/{adoption}/gifts', [GiftBoxController::class, 'store'])->middleware(['auth', 'tenant.member'])->name('tenant.my.gift.store');
    Route::get('/my/plot/{adoption}/gifts/{giftBox}/customize', [GiftBoxController::class, 'customize'])->middleware(['auth', 'tenant.member'])->name('tenant.my.gift.customize');
    Route::post('/my/plot/{adoption}/gifts/{giftBox}/customize', [GiftBoxController::class, 'updateCustomize'])->middleware(['auth', 'tenant.member'])->name('tenant.my.gift.update');

    // 实时监控（auth + 本租户成员：监控涉家人肖像隐私，不公开且限本租户）
    Route::get('/live', [LiveController::class, 'index'])->middleware(['auth', 'tenant.member'])->name('tenant.live.index');
    Route::get('/live/{camera}', [LiveController::class, 'show'])->middleware(['auth', 'tenant.member'])->name('tenant.live.show');

    // 商户后台（租户管理员，限本人租户）
    Route::prefix('admin')->middleware(['auth', 'role:tenant_admin'])->group(function () {
        Route::get('/', [AdminDashboard::class, 'index'])->name('tenant.admin.dashboard');
        Route::post('/adoptions/{adoption}/refund', [AdminAdoptions::class, 'refund'])->name('tenant.admin.refund');

        Route::get('/cameras', [AdminCameras::class, 'index'])->name('tenant.admin.cameras.index');
        Route::get('/cameras/create', [AdminCameras::class, 'create'])->name('tenant.admin.cameras.create');
        Route::post('/cameras', [AdminCameras::class, 'store'])->name('tenant.admin.cameras.store');
        Route::get('/cameras/{camera}/edit', [AdminCameras::class, 'edit'])->name('tenant.admin.cameras.edit');
        Route::put('/cameras/{camera}', [AdminCameras::class, 'update'])->name('tenant.admin.cameras.update');
        Route::delete('/cameras/{camera}', [AdminCameras::class, 'destroy'])->name('tenant.admin.cameras.destroy');

        Route::get('/trace-codes', [AdminTraceCodes::class, 'index'])->name('tenant.admin.trace-codes.index');
        Route::get('/trace-codes/create', [AdminTraceCodes::class, 'create'])->name('tenant.admin.trace-codes.create');
        Route::post('/trace-codes', [AdminTraceCodes::class, 'store'])->name('tenant.admin.trace-codes.store');
        Route::get('/trace-codes/print', [AdminTraceCodes::class, 'print'])->name('tenant.admin.trace-codes.print');

        Route::get('/adoptions', [AdminAdoptions::class, 'index'])->name('tenant.admin.adoptions.index');
        Route::get('/adoptions/create', [AdminAdoptions::class, 'create'])->name('tenant.admin.adoptions.create');
        Route::post('/adoptions', [AdminAdoptions::class, 'store'])->name('tenant.admin.adoptions.store');
        Route::get('/farm-logs', [AdminFarmLogs::class, 'index'])->name('tenant.admin.farm-logs.index');
        Route::delete('/farm-logs/{farm_log}', [AdminFarmLogs::class, 'destroy'])->name('tenant.admin.farm-logs.destroy');

        // F1 地块动态管理（admin CRUD + 故事）
        Route::get('/plots', [AdminPlots::class, 'index'])->name('tenant.admin.plots.index');
        Route::get('/plots/create', [AdminPlots::class, 'create'])->name('tenant.admin.plots.create');
        Route::post('/plots', [AdminPlots::class, 'store'])->name('tenant.admin.plots.store');
        Route::get('/plots/{plot}/edit', [AdminPlots::class, 'edit'])->name('tenant.admin.plots.edit');
        Route::put('/plots/{plot}', [AdminPlots::class, 'update'])->name('tenant.admin.plots.update');
        Route::delete('/plots/{plot}', [AdminPlots::class, 'destroy'])->name('tenant.admin.plots.destroy');
        Route::post('/plots/{plot}/story', [AdminPlots::class, 'updateStory'])->name('tenant.admin.plots.story');

        Route::get('/deliveries', [AdminDeliveries::class, 'index'])->name('tenant.admin.deliveries.index');
        Route::get('/deliveries/create', [AdminDeliveries::class, 'create'])->name('tenant.admin.deliveries.create');
        Route::post('/deliveries', [AdminDeliveries::class, 'store'])->name('tenant.admin.deliveries.store');
        Route::post('/deliveries/{delivery}/ship', [AdminDeliveries::class, 'ship'])->name('tenant.admin.deliveries.ship');
        Route::get('/deliveries/print', [AdminDeliveries::class, 'print'])->name('tenant.admin.deliveries.print');

        // A2 统一发货台：聚合配送 + 礼盒两个出库队列
        Route::get('/shipments', [AdminShipments::class, 'index'])->name('tenant.admin.shipments.index');
        Route::post('/shipments/deliveries/{delivery}/ship', [AdminShipments::class, 'shipDelivery'])->name('tenant.admin.shipments.delivery.ship');
        Route::post('/shipments/gifts/{giftBox}/ship', [AdminShipments::class, 'shipGift'])->name('tenant.admin.shipments.gift.ship');
        Route::post('/shipments/gifts/{giftBox}/making', [AdminShipments::class, 'makeGift'])->name('tenant.admin.shipments.gift.making');

        Route::get('/adjustments', [AdminAdjustments::class, 'index'])->name('tenant.admin.adjustments.index');
        Route::post('/adjustments/settle', [AdminAdjustments::class, 'settle'])->name('tenant.admin.adjustments.settle');
        Route::post('/adjustments/apply-all', [AdminAdjustments::class, 'applyAll'])->name('tenant.admin.adjustments.apply-all');
        Route::post('/adjustments/{adjustment}/apply', [AdminAdjustments::class, 'apply'])->name('tenant.admin.adjustments.apply');

        Route::get('/gift-boxes', [AdminGiftBoxes::class, 'index'])->name('tenant.admin.gift-boxes.index');
        Route::post('/gift-boxes/{giftBox}/making', [AdminGiftBoxes::class, 'making'])->name('tenant.admin.gift-boxes.making');
        Route::post('/gift-boxes/{giftBox}/ship', [AdminGiftBoxes::class, 'ship'])->name('tenant.admin.gift-boxes.ship');
        Route::post('/gift-boxes/{giftBox}/delivered', [AdminGiftBoxes::class, 'delivered'])->name('tenant.admin.gift-boxes.delivered');
        Route::get('/gift-boxes/print', [AdminGiftBoxes::class, 'print'])->name('tenant.admin.gift-boxes.print');

        Route::get('/promotions', [AdminPromotions::class, 'index'])->name('tenant.admin.promotions.index');
        Route::get('/promotions/create', [AdminPromotions::class, 'create'])->name('tenant.admin.promotions.create');
        Route::post('/promotions', [AdminPromotions::class, 'store'])->name('tenant.admin.promotions.store');

        Route::get('/settings', [AdminSettings::class, 'edit'])->name('tenant.admin.settings.edit');
        Route::put('/settings', [AdminSettings::class, 'update'])->name('tenant.admin.settings.update');
        Route::get('/short-links', [AdminShortLinks::class, 'index'])->name('tenant.admin.short-links.index');
        Route::get('/short-links/create', [AdminShortLinks::class, 'create'])->name('tenant.admin.short-links.create');
        Route::post('/short-links', [AdminShortLinks::class, 'store'])->name('tenant.admin.short-links.store');
    });

    // 家人端（家人/租户管理员）
    Route::prefix('family')->middleware(['auth', 'role:family,tenant_admin'])->group(function () {
        Route::get('/', [FamilyDashboard::class, 'index'])->name('tenant.family.dashboard');

        // 录入（按 farm_members.permission_scope 限权，tenant_admin 直通）
        Route::get('/logs/create', [FamilyLogs::class, 'create'])->name('tenant.family.logs.create');
        Route::post('/logs', [FamilyLogs::class, 'store'])->name('tenant.family.logs.store');
        Route::get('/logs/{farm_log}/edit', [FamilyLogs::class, 'edit'])->name('tenant.family.logs.edit');
        Route::post('/logs/{farm_log}', [FamilyLogs::class, 'update'])->name('tenant.family.logs.update');
        Route::get('/fertilizer/create', [FamilyFertilizer::class, 'create'])->name('tenant.family.fertilizer.create');
        Route::post('/fertilizer', [FamilyFertilizer::class, 'store'])->name('tenant.family.fertilizer.store');
        Route::get('/fertilizer/{batch}/edit', [FamilyFertilizer::class, 'edit'])->name('tenant.family.fertilizer.edit');
        Route::post('/fertilizer/{batch}', [FamilyFertilizer::class, 'update'])->name('tenant.family.fertilizer.update');
        Route::get('/harvest/create', [FamilyHarvest::class, 'create'])->name('tenant.family.harvest.create');
        Route::post('/harvest', [FamilyHarvest::class, 'store'])->name('tenant.family.harvest.store');
        Route::get('/harvest/{harvest}/edit', [FamilyHarvest::class, 'edit'])->name('tenant.family.harvest.edit');
        Route::post('/harvest/{harvest}', [FamilyHarvest::class, 'update'])->name('tenant.family.harvest.update');

        // F1.2 田地录入（家人建/改，限本基地；删除走 admin）
        Route::get('/plots', [FamilyPlots::class, 'index'])->name('tenant.family.plots.index');
        Route::get('/plots/create', [FamilyPlots::class, 'create'])->name('tenant.family.plots.create');
        Route::post('/plots', [FamilyPlots::class, 'store'])->name('tenant.family.plots.store');
        Route::get('/plots/{plot}/edit', [FamilyPlots::class, 'edit'])->name('tenant.family.plots.edit');
        Route::put('/plots/{plot}', [FamilyPlots::class, 'update'])->name('tenant.family.plots.update');
    });
});
