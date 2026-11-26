@extends('layouts.app')
@section('content')
<section class="admin-wrap"><div class="admin-top"><div><span class="eyebrow">ORDER DETAILS</span><h1>{{ $order->order_number }}</h1></div><a class="text-link" href="{{ route('admin.orders') }}">بازگشت به سفارش‌ها ←</a></div>
<div class="admin-panel"><h2>مشخصات مشتری</h2><p>{{ $order->customer_name }} — {{ $order->mobile }}</p><p>{{ $order->email }}</p><p>{{ $order->shipping_address }}</p><h2>اقلام سفارش</h2>@foreach($order->items as $item)<p>{{ $item->product_name }} × {{ $item->quantity }} — {{ number_format($item->line_total) }} تومان</p>@endforeach
<hr><p>جمع کل: <strong>{{ number_format($order->total) }} تومان</strong></p><p>وضعیت پرداخت: {{ $order->payment_status }}</p><p>پیامک تأیید پرداخت: {{ $order->sms_sent_at ? $order->sms_sent_at->format('Y/m/d H:i') : 'ارسال نشده یا در انتظار تنظیم پنل' }}</p>@if($order->payment_status==='paid' && !$order->stock_deducted_at)
<form method="POST" action="{{ route('admin.orders.reconcile',$order) }}" class="order-status-form">
@csrf
<button class="button button-dark" type="submit">تلاش مجدد برای کسر موجودی و آماده‌سازی</button>
</form>
@endif
<form method="POST" action="{{ route('admin.orders.update',$order) }}" class="order-status-form">@csrf @method('PATCH')<label>وضعیت سفارش<select name="status">@foreach(['pending'=>'در انتظار','processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده'] as $value=>$label)<option value="{{ $value }}" @selected($order->status===$value)>{{ $label }}</option>@endforeach</select></label><button class="button button-dark">ذخیره وضعیت</button></form></div></section>
@endsection