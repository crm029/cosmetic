@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">ORDER CARE</span><h1>پیگیری <em>سفارش</em></h1><p>شماره سفارش و موبایلی را که هنگام خرید ثبت کردی وارد کن.</p></section>
<section class="section-block tracking-wrap">
@if($errors->any())<p class="notice-error">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('orders.track.lookup') }}" class="tracking-form">@csrf
<label>شماره سفارش<input name="order_number" value="{{ old('order_number') }}" placeholder="CS-..." required></label>
<label>شماره موبایل<input name="mobile" value="{{ old('mobile') }}" inputmode="numeric" placeholder="09xxxxxxxxx" required></label>
<button class="button button-dark" type="submit">پیگیری سفارش ←</button></form>
@if($order)
<div class="tracking-result"><span class="eyebrow">ORDER {{ $order->order_number }}</span><h2>{{ $order->customer_name }}</h2><p>وضعیت سفارش: <strong>{{ ['pending'=>'در انتظار پرداخت','processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده','paid_stock_issue'=>'نیازمند بررسی پشتیبانی'][$order->status] ?? $order->status }}</strong></p><p>وضعیت پرداخت: {{ $order->payment_status==='paid'?'پرداخت‌شده':'پرداخت‌نشده' }}</p><p>مبلغ سفارش: {{ number_format($order->total) }} تومان</p>
@if($order->shipping_carrier || $order->tracking_code)
<div class="shipment-tracking">
<h3>اطلاعات ارسال</h3>
@if($order->shipping_carrier)<p>روش ارسال: <strong>{{ $order->shipping_carrier }}</strong></p>@endif
@if($order->tracking_code)<p>کد رهگیری: <strong>{{ $order->tracking_code }}</strong></p>@endif
@if($order->shipped_at)<p>تاریخ ارسال: {{ $order->shipped_at->format('Y/m/d H:i') }}</p>@endif
@if($order->delivered_at)<p>تاریخ تحویل: {{ $order->delivered_at->format('Y/m/d H:i') }}</p>@endif
</div>
@endif
<div class="tracking-items">@foreach($order->items as $item)<p>{{ $item->product_name }} × {{ $item->quantity }}</p>@endforeach</div></div>
@endif
</section>
@endsection