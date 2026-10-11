@extends('layouts.dashboard')

@section('title', '站点设置')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', []) }}">后台</a>
  <a href="{{ route('tenant.admin.short-links.index', []) }}">短链接</a>
  <a href="{{ route('tenant.home', []) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">站点设置</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form id="settings-form" method="POST" action="{{ route('tenant.admin.settings.update', []) }}">
    @csrf
    @method('PUT')

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>品牌</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>品牌主色(如 #3F6B4F)</label>
          <input class="input" name="brand_primary" maxlength="20" value="{{ old('brand_primary', $tenant->settings['brand']['primary'] ?? config('site.defaults.brand.primary')) }}">
        </div>
        <div class="field">
          <label>品牌辅色(如 #A8791E)</label>
          <input class="input" name="brand_accent" maxlength="20" value="{{ old('brand_accent', $tenant->settings['brand']['accent'] ?? config('site.defaults.brand.accent')) }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>SEO / 分享</span></div>
      <div class="field">
        <label>站点标题(SEO title)</label>
        <input class="input" name="seo_title" maxlength="60" value="{{ old('seo_title', $tenant->settings['seo']['title'] ?? config('site.defaults.title')) }}">
      </div>
      <div class="field">
        <label>站点描述(SEO / 分享 description)</label>
        <textarea class="textarea" name="seo_description" rows="3" maxlength="200">{{ old('seo_description', $tenant->settings['seo']['description'] ?? config('site.defaults.description')) }}</textarea>
      </div>
      <div class="field">
        <label>分享图片 URL(og:image,需绝对地址)</label>
        <input class="input" name="seo_image" maxlength="500" value="{{ old('seo_image', $tenant->settings['seo']['image'] ?? '') }}" placeholder="https://.../logo.png">
      </div>
    </div>

    <div class="section">
      <div class="section-title"><span>页脚 / 联系方式</span></div>
      <div class="field">
        <label>页脚版权</label>
        <input class="input" name="footer_copyright" maxlength="120" value="{{ old('footer_copyright', $tenant->settings['footer_copyright'] ?? config('site.defaults.footer_copyright')) }}">
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>ICP 备案号</label>
          <input class="input" name="icp_number" maxlength="60" value="{{ old('icp_number', $tenant->settings['icp_number'] ?? '') }}">
        </div>
        <div class="field">
          <label>联系方式(客服 / 微信)</label>
          <input class="input" name="contact" maxlength="200" value="{{ old('contact', $tenant->settings['contact'] ?? '') }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>定价（锚点示意，可改）</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>分地档年费(元)</label>
          <input class="input" type="number" name="fendi_yearly" min="0" value="{{ old('fendi_yearly', $pricing['fendi_yearly'] ?? null) }}">
        </div>
        <div class="field">
          <label>单株档年费(元)</label>
          <input class="input" type="number" name="zhu_yearly" min="0" value="{{ old('zhu_yearly', $pricing['zhu_yearly'] ?? null) }}">
        </div>
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>试认养体验包 最低价(元)</label>
          <input class="input" type="number" name="trial_pack_min" min="0" value="{{ old('trial_pack_min', $pricing['trial_pack']['min'] ?? null) }}">
        </div>
        <div class="field">
          <label>试认养体验包 最高价(元)</label>
          <input class="input" type="number" name="trial_pack_max" min="0" value="{{ old('trial_pack_max', $pricing['trial_pack']['max'] ?? null) }}">
        </div>
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>分地保底产量(kg)</label>
          <input class="input" type="number" step="0.1" name="guarantee_fendi" min="0" value="{{ old('guarantee_fendi', $pricing['guarantee_kg']['fendi'] ?? null) }}">
          <div class="field-hint">不足由丰欠共担池补齐；上线前先过成本核算。</div>
        </div>
        <div class="field">
          <label>单株保底产量(kg)</label>
          <input class="input" type="number" step="0.1" name="guarantee_zhu" min="0" value="{{ old('guarantee_zhu', $pricing['guarantee_kg']['zhu'] ?? null) }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>营销（老带新 / 立减 / 续费抵用）</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>老带新·新人抵(元)</label>
          <input class="input" type="number" name="referral_new" min="0" value="{{ old('referral_new', $promotion['referral']['new'] ?? null) }}">
        </div>
        <div class="field">
          <label>老带新·推荐人抵(元)</label>
          <input class="input" type="number" name="referral_referrer" min="0" value="{{ old('referral_referrer', $promotion['referral']['referrer'] ?? null) }}">
        </div>
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>新客立减(元)</label>
          <input class="input" type="number" name="new_customer" min="0" value="{{ old('new_customer', $promotion['new_customer'] ?? null) }}">
        </div>
        <div class="field">
          <label>续费抵用(元)</label>
          <input class="input" type="number" name="renewal" min="0" value="{{ old('renewal', $promotion['renewal'] ?? null) }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>分销佣金（M4 预留，≤10% 合规）</span></div>
      <div class="card-grid grid-3">
        <div class="field">
          <label>红人佣金率(%)</label>
          <input class="input" type="number" name="rate_red" min="0" max="10" value="{{ old('rate_red', $commission['rates']['red'] ?? null) }}">
        </div>
        <div class="field">
          <label>达人佣金率(%)</label>
          <input class="input" type="number" name="rate_expert" min="0" max="10" value="{{ old('rate_expert', $commission['rates']['expert'] ?? null) }}">
        </div>
        <div class="field">
          <label>合伙人佣金率(%)</label>
          <input class="input" type="number" name="rate_partner" min="0" max="10" value="{{ old('rate_partner', $commission['rates']['partner'] ?? null) }}">
        </div>
      </div>
      <div class="field">
        <label>认养冷却期(天，过后佣金转可用)</label>
        <input class="input" type="number" name="cooldown_days" min="0" max="90" value="{{ old('cooldown_days', $commission['cooldown_days'] ?? null) }}">
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>会员阶梯（M5 预留，近365天消费门槛）</span></div>
      <div class="card-grid grid-3">
        <div class="field">
          <label>红人门槛(元)</label>
          <input class="input" type="number" name="tier_red" min="0" value="{{ old('tier_red', $member['tiers']['red'] ?? null) }}">
        </div>
        <div class="field">
          <label>达人门槛(元)</label>
          <input class="input" type="number" name="tier_expert" min="0" value="{{ old('tier_expert', $member['tiers']['expert'] ?? null) }}">
        </div>
        <div class="field">
          <label>合伙人门槛(元)</label>
          <input class="input" type="number" name="tier_partner" min="0" value="{{ old('tier_partner', $member['tiers']['partner'] ?? null) }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>合同模板（M3 预留）</span></div>
      <div class="field">
        <label>合同条款版本</label>
        <input class="input" name="contract_template_version" maxlength="20" value="{{ old('contract_template_version', $contract['template_version'] ?? 'v1') }}">
        <div class="field-hint">条款正文由 M3 合同模块编辑，此处仅版本号。</div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>首页内容（前台首页文案，保存即生效）</span></div>

      <div class="field">
        <label>主标题</label>
        <input class="input" name="hero_title" maxlength="60" value="{{ old('hero_title', $homepage['hero_title'] ?? '') }}">
      </div>
      <div class="field">
        <label>副标题（{tenant} 会被替换成站点名）</label>
        <textarea class="textarea" name="hero_lede" rows="2" maxlength="300">{{ old('hero_lede', $homepage['hero_lede'] ?? '') }}</textarea>
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>眉标·主（高亮）</label>
          <input class="input" name="eyebrow_primary" maxlength="60" value="{{ old('eyebrow_primary', $homepage['eyebrow_primary'] ?? '') }}">
        </div>
        <div class="field">
          <label>眉标·次（弱化）</label>
          <input class="input" name="eyebrow_secondary" maxlength="60" value="{{ old('eyebrow_secondary', $homepage['eyebrow_secondary'] ?? '') }}">
        </div>
      </div>

      <hr class="divider" style="margin:18px 0">

      <label class="agreement-check">
        <input type="checkbox" name="show_points" value="1" {{ ($homepage['show_points'] ?? true) ? 'checked' : '' }}>
        <span>显示「卖点」区块</span>
      </label>
      <div class="field">
        <label>区块标题</label>
        <input class="input" name="points_title" maxlength="30" value="{{ old('points_title', $homepage['points_title'] ?? '') }}">
      </div>
      <div id="rows-points">
        @foreach (($homepage['points'] ?? []) as $p)
          <div class="card-grid grid-3 row-item">
            <div class="field"><input class="input" type="text" name="points[][title]" maxlength="30" required placeholder="小标题" value="{{ $p['title'] ?? '' }}"></div>
            <div class="field"><input class="input" type="text" name="points[][body]" maxlength="120" required placeholder="说明" value="{{ $p['body'] ?? '' }}"></div>
            <div class="field"><input class="input" type="text" name="points[][note]" maxlength="80" placeholder="备注（可选）" value="{{ $p['note'] ?? '' }}"></div>
            <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-add="rows-points" data-tpl="tpl-point" data-max="6">＋ 加一条卖点（最多 6 条）</button>

      <hr class="divider" style="margin:18px 0">

      <label class="agreement-check">
        <input type="checkbox" name="show_flow" value="1" {{ ($homepage['show_flow'] ?? true) ? 'checked' : '' }}>
        <span>显示「认养流程」区块</span>
      </label>
      <div class="field">
        <label>区块标题</label>
        <input class="input" name="flow_title" maxlength="30" value="{{ old('flow_title', $homepage['flow_title'] ?? '') }}">
      </div>
      <div id="rows-flow">
        @foreach (($homepage['flow_steps'] ?? []) as $step)
          <div class="card-grid grid-3 row-item">
            <div class="field"><input class="input" type="text" name="flow_steps[][num]" maxlength="4" required placeholder="序号(01)" value="{{ $step['num'] ?? '' }}"></div>
            <div class="field"><input class="input" type="text" name="flow_steps[][label]" maxlength="20" required placeholder="步骤名" value="{{ $step['label'] ?? '' }}"></div>
            <div class="field"><input class="input" type="text" name="flow_steps[][note]" maxlength="60" placeholder="说明（可选）" value="{{ $step['note'] ?? '' }}"></div>
            <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-add="rows-flow" data-tpl="tpl-flow" data-max="8">＋ 加一步流程（最多 8 步）</button>
      <div class="card-grid grid-3" style="margin-top:14px">
        <div class="field">
          <label>结语·眉标</label>
          <input class="input" name="cta_note_eyebrow" maxlength="20" value="{{ old('cta_note_eyebrow', $homepage['cta_note_eyebrow'] ?? '') }}">
        </div>
        <div class="field">
          <label>结语·正文</label>
          <input class="input" name="cta_note_body" maxlength="200" value="{{ old('cta_note_body', $homepage['cta_note_body'] ?? '') }}">
        </div>
        <div class="field">
          <label>结语按钮文案</label>
          <input class="input" name="cta_button" maxlength="20" value="{{ old('cta_button', $homepage['cta_button'] ?? '') }}">
        </div>
      </div>

      <hr class="divider" style="margin:18px 0">

      <label class="agreement-check">
        <input type="checkbox" name="show_faq" value="1" {{ ($homepage['show_faq'] ?? true) ? 'checked' : '' }}>
        <span>显示「常见问题」区块</span>
      </label>
      <div class="field">
        <label>区块标题</label>
        <input class="input" name="faq_title" maxlength="30" value="{{ old('faq_title', $homepage['faq_title'] ?? '') }}">
      </div>
      <div id="rows-faq">
        @foreach (($homepage['faq'] ?? []) as $item)
          <div class="card-grid grid-2 row-item">
            <div class="field"><input class="input" type="text" name="faq[][q]" maxlength="60" required placeholder="问题" value="{{ $item['q'] ?? '' }}"></div>
            <div class="field"><input class="input" type="text" name="faq[][a]" maxlength="200" placeholder="答案（可选）" value="{{ $item['a'] ?? '' }}"></div>
            <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-add="rows-faq" data-tpl="tpl-faq" data-max="10">＋ 加一条问答（最多 10 条）</button>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="submit">保存设置</button>
  </form>

  {{-- 行式字段的增行模板：复名 points[]/flow_steps[]/faq[] 让 PHP 自动转成列表，增删行不用重排下标。 --}}
  <template id="tpl-point">
    <div class="card-grid grid-3 row-item">
      <div class="field"><input class="input" type="text" name="points[][title]" maxlength="30" required placeholder="小标题"></div>
      <div class="field"><input class="input" type="text" name="points[][body]" maxlength="120" required placeholder="说明"></div>
      <div class="field"><input class="input" type="text" name="points[][note]" maxlength="80" placeholder="备注（可选）"></div>
      <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
    </div>
  </template>
  <template id="tpl-flow">
    <div class="card-grid grid-3 row-item">
      <div class="field"><input class="input" type="text" name="flow_steps[][num]" maxlength="4" required placeholder="序号(01)"></div>
      <div class="field"><input class="input" type="text" name="flow_steps[][label]" maxlength="20" required placeholder="步骤名"></div>
      <div class="field"><input class="input" type="text" name="flow_steps[][note]" maxlength="60" placeholder="说明（可选）"></div>
      <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
    </div>
  </template>
  <template id="tpl-faq">
    <div class="card-grid grid-2 row-item">
      <div class="field"><input class="input" type="text" name="faq[][q]" maxlength="60" required placeholder="问题"></div>
      <div class="field"><input class="input" type="text" name="faq[][a]" maxlength="200" placeholder="答案（可选）"></div>
      <div class="field"><button type="button" class="btn btn-ghost btn-sm">删</button></div>
    </div>
  </template>

  <script>
    (function () {
      var form = document.getElementById('settings-form');
      if (!form) return;

      // 增行：到上限就停（服务端 max 校验仍是真正闸门）
      form.querySelectorAll('[data-add]').forEach(function (btn) {
        var list = document.getElementById(btn.dataset.add);
        var tpl = document.getElementById(btn.dataset.tpl);
        if (!list || !tpl) return;
        btn.addEventListener('click', function () {
          if (list.children.length >= Number(btn.dataset.max || 0)) return;
          list.appendChild(tpl.content.cloneNode(true));
        });
      });

      // 删行：保留至少一行，避免区块被清空成空标题
      form.addEventListener('click', function (e) {
        if (e.target.tagName !== 'BUTTON') return;
        var item = e.target.closest('.row-item');
        if (item && item.parentNode.children.length > 1) item.remove();
      });

      // 提交前清掉全空行（浏览器 required 已拦提交，这里是防线二）
      form.addEventListener('submit', function () {
        form.querySelectorAll('.row-item').forEach(function (item) {
          var filled = Array.prototype.some.call(item.querySelectorAll('input'), function (i) {
            return i.value !== '';
          });
          if (!filled) item.remove();
        });
      });
    })();
  </script>

  <p class="note text-xs" style="margin-top:14px">
    品牌色即时反映到前台 / 后台主题;SEO 文案在分享卡片与搜索引擎预览中生效。
    定价/营销/分销/会员为锚点示意，实际值须先过成本核算与法律核。
  </p>
@endsection