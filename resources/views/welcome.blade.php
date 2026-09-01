<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@vite('resources/css/app.css')
<title>光彩云村庄 · 云端认养真实田块</title>
<style>
  /* 入口页专属:全屏品牌画面 + 多租户导航条 */
  .land {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background:
      radial-gradient(1100px 620px at 12% -12%, var(--color-brand-50), transparent 60%),
      radial-gradient(900px 520px at 92% 112%, var(--color-accent-300), transparent 55%),
      var(--ds-bg-base);
  }
  .land-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 40px;
    border-bottom: 1px solid var(--ds-border-1);
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
  }
  .land-mark {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-serif);
    font-size: 20px;
    font-weight: 700;
    color: var(--color-brand-500);
    text-decoration: none;
  }
  .land-mark .brand-dot {
    width: 11px; height: 11px;
    border-radius: 999px;
    background: var(--color-brand-500);
    box-shadow: 0 0 0 4px var(--color-brand-50);
  }
  .land-nav-links {
    display: flex;
    align-items: center;
    gap: 24px;
    font-size: var(--ds-body-s);
  }
  .land-nav-links a {
    color: var(--ds-text-soft);
    text-decoration: none;
  }
  .land-nav-links a:hover { color: var(--color-brand-500); }

  .land-hero {
    flex: 1;
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    align-items: center;
    gap: 48px;
    padding: 72px 40px 56px;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
  }
  .land-eyebrow {
    font-size: var(--ds-body-xs);
    letter-spacing: 0.18em;
    color: var(--ds-text-mute);
    font-weight: 600;
    text-transform: uppercase;
  }
  .land-title {
    font-family: var(--font-serif);
    font-size: 44px;
    font-weight: 700;
    line-height: 1.18;
    letter-spacing: -0.015em;
    color: var(--ds-text);
    margin: 14px 0 18px;
  }
  .land-title em {
    font-style: normal;
    color: var(--color-brand-500);
  }
  .land-lede {
    font-size: var(--ds-body);
    color: var(--ds-text-soft);
    line-height: 1.75;
    margin-bottom: 28px;
    max-width: 520px;
  }
  .land-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .land-note {
    font-size: var(--ds-body-xs);
    color: var(--ds-text-mute);
    margin-top: 20px;
  }
  .land-note .sep { margin: 0 8px; color: var(--ds-border-2); }

  /* 右侧品牌面板:暖调渐变 + 数据角标 */
  .land-panel {
    background: linear-gradient(140deg, var(--ds-bg-layer-1) 0%, var(--color-brand-50) 100%);
    border: 1px solid var(--ds-border-1);
    border-radius: var(--ds-r-xl);
    padding: 32px;
    box-shadow: var(--ds-shadow-elev);
    position: relative;
    overflow: hidden;
  }
  .land-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
  }
  .land-panel-mark {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: var(--ds-body-xs);
    letter-spacing: 0.16em;
    color: var(--ds-text-mute);
    font-weight: 600;
  }
  .land-panel-mark .dot {
    width: 8px; height: 8px;
    border-radius: 999px;
    background: var(--color-available-500);
    box-shadow: 0 0 0 3px var(--color-ok-bg);
  }
  .land-panel-tiles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  .land-tile {
    background: var(--ds-bg-layer-1);
    border: 1px solid var(--ds-border-1);
    border-radius: var(--ds-r-md);
    padding: 18px 16px;
    box-shadow: var(--ds-shadow-card);
  }
  .land-tile .v {
    font-family: var(--font-serif);
    font-size: 28px;
    font-weight: 700;
    color: var(--color-brand-500);
    line-height: 1;
  }
  .land-tile .v .u {
    font-size: 14px;
    font-weight: 600;
    margin-left: 2px;
  }
  .land-tile .k {
    font-size: var(--ds-body-xs);
    color: var(--ds-text-mute);
    margin-top: 8px;
  }
  .land-panel-tagline {
    margin-top: 22px;
    padding-top: 20px;
    border-top: 1px dashed var(--ds-border-2);
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .land-panel-tagline .seal {
    flex-shrink: 0;
    font-family: var(--font-serif);
    font-size: 20px;
    font-weight: 700;
    color: var(--color-accent-500);
    line-height: 1.1;
  }
  .land-panel-tagline .tag-txt {
    font-size: var(--ds-body-s);
    color: var(--ds-text-soft);
    line-height: 1.6;
  }

  .land-foot {
    border-top: 1px solid var(--ds-border-1);
    padding: 20px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    font-size: var(--ds-body-xs);
    color: var(--ds-text-mute);
  }
  .land-foot-tags { display: flex; gap: 8px; flex-wrap: wrap; }
  .land-foot-tag {
    padding: 5px 12px;
    background: var(--ds-bg-layer-1);
    border: 1px solid var(--ds-border-1);
    border-radius: 999px;
    font-size: var(--ds-body-xs);
    color: var(--ds-text-soft);
  }

  @media (max-width: 820px) {
    .land-nav { padding: 14px 20px; }
    .land-nav-links a:not(.btn) { display: none; }
    .land-hero { grid-template-columns: 1fr; padding: 40px 20px 32px; gap: 28px; }
    .land-title { font-size: 32px; }
  }
</style>
</head>
<body>
<div class="land">
  <nav class="land-nav">
    <a class="land-mark" href="/">
      <span class="brand-dot" aria-hidden="true"></span>
      光彩云村庄
    </a>
    <div class="land-nav-links">
      <a href="https://vour.cn">品牌站点</a>
      <a href="https://foxfun.cn">技术服务</a>
      <a href="https://nxlb.com.cn">自有有机肥</a>
      <a class="btn btn-ghost btn-sm" href="/login">登录</a>
    </div>
  </nav>

  <div class="land-hero">
    <div>
      <div class="land-eyebrow">CLOUD VILLAGE · 宁夏红寺堡</div>
      <h1 class="land-title">认养一块真实的<em>宁夏枸杞田</em></h1>
      <p class="land-lede">
        每块田有独立编号、坐标与溯源码,家人实地种植、农事一季一季录入。
        你认养的田会变成村庄平台样板田,把一粒枸杞种成一份共建。
      </p>
      <div class="land-cta">
        <a class="btn btn-primary btn-lg" href="/adopt">去看田块</a>
        <a class="btn btn-ghost btn-lg" href="/live">实时监控</a>
      </div>
      <p class="land-note">
        <span>6 亩样板田起步</span><span class="sep">·</span>
        <span>红寺堡在地种植</span><span class="sep">·</span>
        <span>全程可查</span>
      </p>
    </div>

    <aside class="land-panel">
      <div class="land-panel-head">
        <span class="land-panel-mark"><span class="dot" aria-hidden="true"></span>样板田 · 进行中</span>
        <span class="text-xs muted">红寺堡 37.3°N</span>
      </div>

      <div class="land-panel-tiles">
        <div class="land-tile">
          <div class="v">6<span class="u">亩</span></div>
          <div class="k">样板田认养面积</div>
        </div>
        <div class="land-tile">
          <div class="v">100<span class="u">%</span></div>
          <div class="k">本地劳力种植</div>
        </div>
        <div class="land-tile">
          <div class="v">NXLB</div>
          <div class="k">自有有机肥厂批次可查</div>
        </div>
        <div class="land-tile">
          <div class="v">1<span class="u">码</span></div>
          <div class="k">一箱枸杞一溯源</div>
        </div>
      </div>

      <div class="land-panel-tagline">
        <span class="seal">⚑</span>
        <span class="tag-txt">
          认养即共建 —— 你的田块会成为村庄平台的一部分,带动本地种植户一起把好枸杞做出来。
        </span>
      </div>
    </aside>
  </div>

  <footer class="land-foot">
    <span>© 宁夏花乌巷食品有限公司 · 光彩云村庄</span>
    <div class="land-foot-tags">
      <span class="land-foot-tag">枸杞</span>
      <span class="land-foot-tag">云乡民</span>
      <span class="land-foot-tag">溯源</span>
      <span class="land-foot-tag">认养</span>
    </div>
  </footer>
</div>
</body>
</html>