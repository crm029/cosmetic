@extends('layouts.app')
@section('content')
<section class="admin-wrap">
<div class="admin-top"><div><span class="eyebrow">STORE CONFIGURATION</span><h1>تنظیمات فروشگاه</h1></div><a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a></div>
@if(session('success'))<p class="notice-success">{{ session('success') }}</p>@endif
@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="admin-form settings-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
<h3>مشخصات فروشگاه</h3>
<label>نام فروشگاه<input name="store_name" value="{{ old('store_name',$settings->store_name) }}" maxlength="150" required></label>
<label>شعار کوتاه فروشگاه<input name="tagline" value="{{ old('tagline',$settings->tagline) }}" maxlength="255"></label>
<label class="full-field">لوگوی فروشگاه (حداکثر ۲ مگابایت)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/avif"></label>
@if($settings->logo_path)<img class="settings-logo-preview" src="{{ asset('storage/'.$settings->logo_path) }}" alt="{{ $settings->store_name }}">@endif
<label>شماره پشتیبانی<input name="support_mobile" value="{{ old('support_mobile',$settings->support_mobile) }}" placeholder="09xxxxxxxxx"></label>
<label>ایمیل پشتیبانی<input type="email" name="support_email" value="{{ old('support_email',$settings->support_email) }}"></label>
<label class="full-field">نشانی فروشگاه<textarea name="address" rows="3">{{ old('address',$settings->address) }}</textarea></label>
<h3>هزینه ارسال</h3>
<label>هزینه ارسال (تومان)<input type="number" name="shipping_fee_toman" min="0" value="{{ old('shipping_fee_toman',$settings->shipping_fee_toman) }}" required></label>
<label>حداقل خرید برای ارسال رایگان (تومان)<input type="number" name="free_shipping_threshold_toman" min="0" value="{{ old('free_shipping_threshold_toman',$settings->free_shipping_threshold_toman) }}" required></label>
<p class="settings-hint full-field">اگر حداقل خرید ارسال رایگان را صفر بگذاری، ارسال رایگانِ خودکار غیرفعال می‌شود.</p>
<h3>رنگ‌بندی قالب</h3>
<label>رنگ اصلی<input type="color" name="ink_color" value="{{ old('ink_color',$settings->ink_color) }}" required></label>
<label>رنگ تأکیدی<input type="color" name="accent_color" value="{{ old('accent_color',$settings->accent_color) }}" required></label>
<button class="button button-dark" type="submit">ذخیره تنظیمات ←</button>
</form>
</section>
@endsection