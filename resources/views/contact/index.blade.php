@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">WE ARE HERE FOR YOU</span><h1>با ما در <em>ارتباط</em> باش</h1><p>سؤال، پیشنهاد یا پیگیری داری؟ پیام بفرست تا بررسی کنیم.</p></section>
<section class="section-block contact-layout">
<div class="contact-note"><span class="eyebrow">A NOTE TO OUR TEAM</span><h2>گفت‌وگو از همین‌جا شروع می‌شود.</h2><p>اطلاعات تماس را وارد کن و پیام خودت را برای تیم فروشگاه بفرست. لطفاً برای پیگیری سفارش، از صفحه پیگیری سفارش استفاده کن.</p><a class="text-link" href="{{ route('orders.track') }}">پیگیری سفارش ←</a></div>
<div>@if(session('success'))<p class="notice-success">{{ session('success') }}</p>@endif
@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="contact-form" method="POST" action="{{ route('contact.store') }}">@csrf
<label>نام و نام خانوادگی<input name="name" value="{{ old('name') }}" maxlength="120" required></label>
<label>شماره موبایل (اختیاری)<input name="mobile" value="{{ old('mobile') }}" inputmode="numeric" placeholder="09xxxxxxxxx"></label>
<label>ایمیل (اختیاری)<input type="email" name="email" value="{{ old('email') }}"></label>
<label>موضوع پیام<input name="subject" value="{{ old('subject') }}" maxlength="150"></label>
<label>متن پیام<textarea name="message" rows="6" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea></label>
<div class="contact-honeypot" aria-hidden="true"><label>وب‌سایت<input name="website" tabindex="-1" autocomplete="off"></label></div>
<button class="button button-dark" type="submit">ارسال پیام ←</button></form></div></section>
@endsection