@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">SECURE CHECKOUT</span><h1>تکمیل <em>سفارش</em></h1></section>
<section class="section-block checkout-layout">
<div>@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="checkout-form" method="POST" action="{{ route('checkout.store') }}">@csrf
<label>نام و نام خانوادگی<input name="customer_name" value="{{ old('customer_name') }}" required maxlength="120"></label>
<label>شماره موبایل<input name="mobile" value="{{ old('mobile') }}" inputmode="numeric" placeholder="09xxxxxxxxx" pattern="09[0-9]{9}" required></label>
<label>ایمیل (اختیاری)<input type="email" name="email" value="{{ old('email') }}"></label>
<label>روش دریافت<select name="shipping_method" id="shipping-method"><option value="post" @selected(old('shipping_method',$shippingMethod)==='post')>ارسال با پست / پیک</option><option value="pickup" @selected(old('shipping_method',$shippingMethod)==='pickup')>تحویل حضوری از فروشگاه (رایگان)</option></select></label>
<label>آدرس کامل تحویل<textarea name="shipping_address" rows="4" required>{{ old('shipping_address') }}</textarea><small>برای تحویل حضوری، نشانی فروشگاه: {{ $settings->address ?: 'نشانی فروشگاه در تنظیمات ثبت نشده است.' }}</small></label>
<label>توضیحات سفارش (اختیاری)<textarea name="notes" rows="2">{{ old('notes') }}</textarea></label>
<button class="button button-dark" type="submit">ثبت سفارش و پرداخت <span>←</span></button></form>
<form method="POST" action="{{ route('coupon.apply') }}" class="checkout-form">@csrf<label>کد تخفیف<input name="code" maxlength="40" value="{{ $coupon?->code }}" placeholder="کد تخفیف"></label><button class="button button-outline" type="submit">اعمال کد تخفیف</button></form>
@if($coupon)<form method="POST" action="{{ route('coupon.remove') }}">@csrf @method('DELETE')<button class="text-link" type="submit">حذف کد تخفیف {{ $coupon->code }}</button></form>@endif
</div>
<aside class="checkout-summary"><h3>خلاصه سفارش</h3>@foreach($products as $product)<p>{{ $product->name }} × {{ $cart[$product->id] }} <b>{{ number_format($product->price*$cart[$product->id]) }} تومان</b></p>@endforeach<hr><p>جمع محصولات <b>{{ number_format($subtotal) }} تومان</b></p><p>هزینه ارسال <b>{{ $shipping ? number_format($shipping).' تومان' : 'رایگان' }}</b></p>@if($discount>0)<p>تخفیف ({{ $coupon->code }}) <b>− {{ number_format($discount) }} تومان</b></p>@endif<strong>مبلغ قابل پرداخت <b>{{ number_format($total) }} تومان</b></strong></aside>
</section>
@endsection