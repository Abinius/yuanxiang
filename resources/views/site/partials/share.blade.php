@php
  $shareUrl = $shareUrl ?? url()->current();
  $shareTitle = $shareTitle ?? ($seo['title'] ?? '');
@endphp
<div class="share-bar">
  <span class="share-eyebrow">分享:</span>
  <button class="btn btn-ghost btn-sm" type="button" onclick="copyShareLink('{{ $shareUrl }}', this)">复制链接</button>
  <a class="btn btn-soft btn-sm" target="_blank" rel="noopener"
     href="https://connect.qq.com/widget/shareqq/index.html?url={{ urlencode($shareUrl) }}&title={{ urlencode($shareTitle) }}">QQ</a>
  <a class="btn btn-soft btn-sm" target="_blank" rel="noopener"
     href="https://service.weibo.com/share/share.php?url={{ urlencode($shareUrl) }}&title={{ urlencode($shareTitle) }}">微博</a>
</div>
<p class="note text-xs" style="margin-top:8px">微信内可点右上角 ··· 转发给好友或朋友圈。</p>
<script>
  function copyShareLink(url, btn) {
    function done() { btn.textContent = '已复制'; setTimeout(function () { btn.textContent = '复制链接'; }, 2000); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () { fallbackCopy(url, done); });
    } else { fallbackCopy(url, done); }
  }
  function fallbackCopy(url, done) {
    var ta = document.createElement('textarea');
    ta.value = url; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
  }
</script>