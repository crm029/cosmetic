@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">YOUR BEAUTY ACCOUNT</span><h1>ورود به <em>حساب کاربری</em></h1><p>با شماره موبایل و کد یک‌بارمصرف وارد شو؛ بدون نیاز به ساخت رمز عبور.</p></section>
<section class="section-block account-panel">
@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(session('otp_sent'))<p class="notice-success">{{ session('otp_sent') }}</p>@endif
<form method="POST" action="{{ route('customer.send-code') }}" class="checkout-form">@csrf
<label>شماره موبایل<input name="mobile" value="{{ old('mobile',session('customer_otp_mobile')) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09123456789" required></label>
<button class="button button-dark" type="submit">ارسال کد ورود ←</button></form>
@if(session('customer_otp_mobile'))
<form method="POST" action="{{ route('customer.verify') }}" class="checkout-form">@csrf
<input type="hidden" name="mobile" value="{{ session('customer_otp_mobile') }}">
<label>کد شش‌رقمی پیامک‌شده<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
<label>نام شما (برای حساب جدید)<input name="name" value="{{ old('name') }}" maxlength="120" placeholder="نام و نام خانوادگی"></label>
<button class="button button-dark" type="submit">تأیید و ورود</button></form>@endif
</section>
@endsection