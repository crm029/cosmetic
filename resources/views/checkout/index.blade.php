@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">SECURE CHECKOUT</span><h1>تکمیل <em>سفارش</em></h1></section>
<section class="section-block checkout-layout">
<div>@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="checkout-form" method="POST" action="{{ route('checkout.store') }}">@csrf
<label>نام و نام خانوادگی<input name="customer_name" value="{{ old('customer_name') }}" required maxlength="120"></label>
<label>شماره موبایل<input name="mobile" value="{{ old('mobile') }}" inputmode="numeric" placeholder="09xxxxxxxxx" pattern="09[0-9]{9}" required></label>
<label>ایمیل (اختیاری)<input type="email" name="email" value="{{ old('email') }}"></label>
<label>آدرس کامل تحویل<textarea name="shipping_address" rows="4" required>{{ old('shipping_address') }}</textarea></label>
<label>توضیحات سفارش (اختیاری)<textarea name="notes" rows="2">{{ old('notes') }}</textarea></label>
<button class="button button-dark" type="submit">ثبت سفارش و پرداخت <span>←</span></button></form></div>
<aside class="checkout-summary"><h3>خلاصه سفارش</h3>@foreach($products as $product)<p>{{ $product->name }} × {{ $cart[$product->id] }} <b>{{ number_format($product->price*$cart[$product->id]) }} تومان</b></p>@endforeach<hr><p>جمع محصولات <b>{{ number_format($subtotal) }} تومان</b></p><p>هزینه ارسال <b>{{ $shipping ? number_format($shipping).' تومان' : 'رایگان' }}</b></p><strong>مبلغ قابل پرداخت <b>{{ number_format($total) }} تومان</b></strong></aside>
</section>
@endsection