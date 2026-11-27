@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">ORDER CARE</span><h1>پیگیری <em>سفارش</em></h1><p>شماره سفارش و موبایلی را که هنگام خرید ثبت کردی وارد کن.</p></section>
<section class="section-block tracking-wrap">
@if($errors->any())<p class="notice-error">{{ $errors->first() }}</p>@endif
@if(auth()->check() && !auth()->user()->is_admin)
<form method="POST" action="{{ route('orders.track.lookup') }}" class="tracking-form">@csrf
<label>شماره سفارش<input name="order_number" value="{{ old('order_number') }}" placeholder="CS-..." required></label>
<label>شماره موبایل<input name="mobile" value="{{ auth()->user()->mobile }}" inputmode="numeric" readonly required></label>
<button class="button button-dark" type="submit">پیگیری سفارش ←</button></form>
@else
<p class="notice-info">برای حفظ حریم خصوصی، ابتدا با کد پیامکی وارد حساب مشتری شو و سپس سفارش‌هایت را پیگیری کن.</p><a class="button button-dark" href="{{ route('customer.login') }}">ورود امن مشتری ←</a>
@endif
@if($order)
<div class="tracking-result"><span class="eyebrow">ORDER {{ $order->order_number }}</span><h2>{{ $order->customer_name }}</h2><p>وضعیت سفارش: <strong>{{ ['pending'=>'در انتظار پرداخت','processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده','paid_stock_issue'=>'نیازمند بررسی پشتیبانی','refund_required'=>'پرداخت موفق؛ بازپرداخت در حال بررسی'][$order->status] ?? $order->status }}</strong></p><p>وضعیت پرداخت: {{ ['paid'=>'پرداخت‌شده','unpaid'=>'پرداخت‌نشده','failed'=>'ناموفق','refunded'=>'بازپرداخت‌شده'][$order->payment_status] ?? $order->payment_status }}</p>
@if($order->status==='refund_required')<p class="notice-error">پرداخت شما موفق بوده، اما سفارش پیش از تأیید نهایی لغو شده است. بازپرداخت وجه در حال بررسی است؛ برای پیگیری با پشتیبانی فروشگاه تماس بگیرید.</p>@elseif($order->payment_status==='refunded')<p class="notice-success">بازپرداخت وجه ثبت شده است.</p>@endif<p>تاریخ ثبت: {{ $order->created_at?->format('Y/m/d H:i') }}</p><p>جمع کالاها: {{ number_format($order->subtotal) }} تومان</p><p>روش دریافت: {{ $order->shipping_method==='pickup'?'تحویل حضوری از فروشگاه':'ارسال با پست / پیک' }}</p>@if($order->coupon_code)<p>کد تخفیف: {{ $order->coupon_code }} — مبلغ تخفیف: {{ number_format($order->discount_amount) }} تومان</p>@endif<p>هزینه ارسال: {{ number_format($order->shipping_amount) }} تومان</p><p>مبلغ نهایی سفارش: <strong>{{ number_format($order->total) }} تومان</strong></p>
@if($order->payment_status!=='paid' && $order->status!=='cancelled')
<form method="POST" action="{{ route('orders.pay.retry',$order) }}" class="retry-payment-form">
@csrf
<input type="hidden" name="order_number" value="{{ $order->order_number }}">
<input type="hidden" name="mobile" value="{{ $order->mobile }}">
<button class="button button-dark" type="submit">ادامه و تکمیل پرداخت ←</button>
</form>
@endif
@if($order->shipping_carrier || $order->tracking_code)
<div class="shipment-tracking">
<h3>اطلاعات ارسال</h3>
@if($order->shipping_carrier)<p>روش ارسال: <strong>{{ $order->shipping_carrier }}</strong></p>@endif
@if($order->tracking_code)<p>کد رهگیری: <strong>{{ $order->tracking_code }}</strong></p>@endif
@if($order->shipped_at)<p>تاریخ ارسال: {{ $order->shipped_at->format('Y/m/d H:i') }}</p>@endif
@if($order->delivered_at)<p>تاریخ تحویل: {{ $order->delivered_at->format('Y/m/d H:i') }}</p>@endif
</div>
@endif
<div class="tracking-items"><h3>اقلام سفارش</h3>@foreach($order->items as $item)<p>{{ $item->product_name }} × {{ $item->quantity }} — {{ number_format($item->line_total) }} تومان</p>@endforeach</div></div>
@endif
</section>
@endsection