<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] ?? '' }}">
@if (! empty($seo['keywords']))
  <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] ?? '' }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
@if (! empty($seo['image']))
  <meta property="og:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="summary_large_image">
@if (! empty($seo['image']))
  <meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@if (! empty($seo['canonical']))
  <link rel="canonical" href="{{ $seo['canonical'] }}">
@endif
<meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
