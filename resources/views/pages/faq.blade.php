@extends('layouts.app')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>[
 ['@type'=>'Question','name'=>'چطور سفارش ثبت کنم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'محصول موردنظر را به سبد خرید اضافه کنید، اطلاعات تحویل را وارد کنید و پرداخت را تکمیل کنید.']],
 ['@type'=>'Question','name'=>'برای خرید باید حساب کاربری بسازم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'خرید مهمان امکان‌پذیر است و برای ثبت سفارش نیازی به ساخت حساب کاربری ندارید.']],
 ['@type'=>'Question','name'=>'چطور سفارش را پیگیری کنم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'شماره سفارش و شماره موبایلی را که هنگام خرید ثبت کرده‌اید در صفحه پیگیری سفارش وارد کنید.']],
 ['@type'=>'Question','name'=>'اگر پرداخت نیمه‌کاره ماند چه کنم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'در صفحه پیگیری سفارش، شماره سفارش و موبایل را وارد کنید و اگر سفارش پرداخت‌نشده باشد، گزینه ادامه پرداخت را انتخاب کنید.']],
 ['@type'=>'Question','name'=>'اگر در خرید یا پرداخت مشکلی پیش آمد چه کنم؟','acceptedAnswer'=>['@type'=>'Answer','text'=>'از طریق صفحه تماس با ما پیام بفرستید و شماره سفارش را در متن پیام درج کنید.']]
 ]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
@endpush
@section('content')
<section class="page-intro"><span class="eyebrow">A LITTLE HELP</span><h1>پرسش‌های <em>متداول</em></h1><p>پاسخ چند سؤال رایج درباره خرید و پیگیری سفارش.</p></section>
<section class="section-block faq-list">
<details open><summary>چطور سفارش ثبت کنم؟</summary><p>محصول موردنظر را به سبد خرید اضافه کنید، اطلاعات تحویل را وارد کنید و پرداخت را تکمیل کنید.</p></details>
<details><summary>برای خرید باید حساب کاربری بسازم؟</summary><p>خیر. خرید مهمان امکان‌پذیر است و برای ثبت سفارش نیازی به ساخت حساب کاربری ندارید.</p></details>
<details><summary>چطور سفارش را پیگیری کنم؟</summary><p>شماره سفارش و شماره موبایلی را که هنگام خرید ثبت کرده‌اید در <a href="{{ route('orders.track') }}">صفحه پیگیری سفارش</a> وارد کنید.</p></details>
<details><summary>اگر پرداخت نیمه‌کاره ماند چه کنم؟</summary><p>در صفحه <a href="{{ route('orders.track') }}">پیگیری سفارش</a>، شماره سفارش و موبایل را وارد کن و اگر سفارش پرداخت‌نشده باشد، گزینه ادامه پرداخت را انتخاب کن.</p></details>
<details><summary>اگر در خرید یا پرداخت مشکلی پیش آمد چه کنم؟</summary><p>از طریق <a href="{{ route('contact.index') }}">صفحه تماس با ما</a> پیام بفرستید و شماره سفارش را در متن پیام درج کنید.</p></details>
</section>
@endsection