@extends('layouts.app')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'AboutPage','name'=>'درباره ما','url'=>url()->current(),'description'=>'داستان و رویکرد فروشگاه در انتخاب محصولات مراقبت و زیبایی.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<section class="page-intro"><span class="eyebrow">OUR POINT OF VIEW</span><h1>داستان <em>ما</em></h1><p>زیبایی در انتخاب‌های آگاهانه و مراقبت‌های کوچک روزانه معنا پیدا می‌کند.</p></section>
<section class="section-block about-story"><div class="about-mark">C</div><div><span class="eyebrow">THE BEAUTY EDIT</span><h2>کمتر، بهتر،<br><em>با دقت بیشتر.</em></h2><p>ما باور داریم مراقبت از خود قرار نیست پیچیده باشد. هدف این فروشگاه، فراهم‌کردن تجربه‌ای ساده و دلنشین برای کشف محصولات مراقبت پوست، مو و زیبایی است؛ جایی که اطلاعات محصول، انتخاب و ثبت سفارش در کنار هم قرار می‌گیرند.</p><p>این مجموعه به‌مرور با معرفی محصولات و تجربه‌های تازه کامل‌تر می‌شود.</p><a class="button button-dark" href="{{ route('shop') }}">مشاهده فروشگاه ←</a></div></section>
@endsection