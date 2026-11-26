<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seoTitle ?? $storeSettings->store_name }}</title>
    <meta name="description" content="{{ $seoDescription ?? 'فروشگاه تخصصی محصولات زیبایی و مراقبت پوست و مو.' }}">
    <meta name="robots" content="{{ $noIndex ? 'noindex,nofollow' : 'index,follow' }}">
    <link rel="canonical" href="{{ isset($product) && $product->canonical_url ? $product->canonical_url : request()->url() }}">
    <meta name="theme-color" content="#f4eee8">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle ?? $storeSettings->store_name }}">
    <meta property="og:description" content="{{ $seoDescription ?? 'فروشگاه تخصصی محصولات زیبایی.' }}">
    @if($socialImage)
        <meta property="og:image" content="{{ $socialImage }}">
    @endif
    <meta name="twitter:card" content="{{ $socialImage ? 'summary_large_image' : 'summary' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/store.css') }}">
    @stack('head')
    <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.180.0/examples/jsm/"}}</script>
</head>
<body style="--ink: {{ $storeSettings->ink_color }}; --clay: {{ $storeSettings->accent_color }};">
    <div class="announcement">
        {{ $storeSettings->tagline ?: 'زیبایی، از جزئیات آغاز می‌شود' }}
        <span>✦</span>
        تجربه‌ای تازه در مراقبت روزانه
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark">C</span>
            <span>{{ $storeSettings->store_name }}<small>THE BEAUTY EDIT</small></span>
        </a>
        <nav class="main-nav" aria-label="منوی اصلی">
            <a href="{{ route('home') }}">خانه</a>
            <a href="{{ route('shop') }}">فروشگاه</a>
            <a href="{{ route('orders.track') }}">پیگیری سفارش</a>
            <a href="{{ route('about') }}">داستان ما</a>
            <a href="{{ route('contact.index') }}">ارتباط</a>
        </nav>
        <div class="header-actions">
            <a href="{{ route('shop') }}" aria-label="جستجو">⌕</a>
            <a class="bag-link" href="{{ route('cart.index') }}">سبد خرید <span>{{ array_sum(session('cart',[])) }}</span></a>
        </div>
    </header>

    @if(session('success'))
        <div class="global-notice">{{ session('success') }}</div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer id="footer" class="site-footer">
        <div>
            <a class="brand footer-brand" href="{{ route('home') }}">
                <span class="brand-mark">C</span>
                <span>{{ $storeSettings->store_name }}<small>THE BEAUTY EDIT</small></span>
            </a>
            <p>انتخاب‌هایی برای مراقبت بهتر، هر روز.</p>
        </div>
        <div>
            <strong>راهنمای خرید</strong>
            <a href="{{ route('shop') }}">فروشگاه</a>
            <a href="{{ route('about') }}">درباره ما</a>
            <a href="{{ route('faq') }}">پرسش‌های متداول</a>
            <a href="{{ route('terms') }}">قوانین خرید</a>
            <a href="{{ route('privacy') }}">حریم خصوصی</a>
            <a href="{{ route('orders.track') }}">پیگیری سفارش</a>
        </div>
        <div>
            <strong>همراه شما</strong>
            <a href="{{ route('contact.index') }}">تماس با ما</a>
            <a href="{{ route('contact.index') }}">پشتیبانی</a>
            @if($storeSettings->support_mobile)
                <small>{{ $storeSettings->support_mobile }}</small>
            @endif
            @if($storeSettings->support_email)
                <small>{{ $storeSettings->support_email }}</small>
            @endif
            @if($storeSettings->address)
                <small>{{ $storeSettings->address }}</small>
            @endif
        </div>
        <small class="copyright">© {{ date('Y') }} {{ $storeSettings->store_name }} — تمامی حقوق محفوظ است.</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js" defer></script>
    <script type="module" src="{{ asset('assets/js/store.js') }}"></script>
</body>
</html>
