@extends('layouts.app')
@section('content')
<section class="admin-wrap">@if($errors->any())<p class="notice-error">{{ $errors->first() }}</p>@endif<div class="admin-top"><div><span class="eyebrow">ORDER DETAILS</span><h1>{{ $order->order_number }}</h1></div><a class="text-link" href="{{ route('admin.orders') }}">بازگشت به سفارش‌ها ←</a></div>
<div class="admin-panel"><h2>مشخصات مشتری</h2><p>{{ $order->customer_name }} — {{ $order->mobile }}</p><p>ایمیل: {{ $order->email ?: 'ثبت نشده' }}</p><p>تاریخ ثبت: {{ $order->created_at?->format('Y/m/d H:i') }}</p><p>نشانی ارسال: {{ $order->shipping_address }}</p><h2>اقلام سفارش</h2>@foreach($order->items as $item)<p>{{ $item->product_name }} × {{ $item->quantity }} — هر عدد {{ number_format($item->unit_price) }} تومان | جمع {{ number_format($item->line_total) }} تومان</p>@endforeach
<hr><p>جمع کالاها: {{ number_format($order->subtotal) }} تومان</p><p>هزینه ارسال: {{ number_format($order->shipping_amount) }} تومان</p><p>جمع کل: <strong>{{ number_format($order->total) }} تومان</strong></p><p>وضعیت پرداخت: {{ ['unpaid'=>'پرداخت‌نشده','paid'=>'پرداخت‌شده','failed'=>'ناموفق','refunded'=>'بازپرداخت‌شده'][$order->payment_status] ?? $order->payment_status }}</p>
@if($order->payment_status==='refunded')<p>مرجع بازپرداخت: {{ $order->refund_ref_id }}</p><p>تاریخ ثبت بازپرداخت: {{ $order->refunded_at?->format('Y/m/d H:i') }}</p><p>یادداشت بازپرداخت: {{ $order->refund_note }}</p>@endif<p>وضعیت سفارش: {{ ['pending'=>'در انتظار','processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده','paid_stock_issue'=>'مشکل موجودی؛ نیازمند بررسی دستی','refund_required'=>'پرداخت موفق؛ بازپرداخت وجه نیازمند اقدام دستی'][$order->status] ?? $order->status }}</p><p>پیامک تأیید پرداخت: {{ $order->sms_sent_at ? $order->sms_sent_at->format('Y/m/d H:i') : 'ارسال نشده یا در انتظار تنظیم پنل' }}</p><p>پیامک ارسال مرسوله: {{ $order->shipment_sms_sent_at ? $order->shipment_sms_sent_at->format('Y/m/d H:i') : 'ارسال نشده' }}</p>@if($order->payment_status==='paid' && !$order->stock_deducted_at && $order->status!=='refund_required')
<form method="POST" action="{{ route('admin.orders.reconcile',$order) }}" class="order-status-form">
@csrf
<button class="button button-dark" type="submit">تلاش مجدد برای کسر موجودی و آماده‌سازی</button>
</form>
@endif
@if($order->status==='refund_required')
<p class="notice-error">وجه در درگاه پرداخت شده اما سفارش قبلاً لغو شده است. ابتدا بازپرداخت را در پنل زرین‌پال انجام بده؛ سپس شماره مرجع بازپرداخت را در این بخش ثبت کن. این فرم خودش پولی مسترد نمی‌کند.</p>
<form method="POST" action="{{ route('admin.orders.refund',$order) }}" class="order-status-form">
@csrf
<label>شماره مرجع بازپرداخت درگاه<input name="refund_ref_id" maxlength="120" required></label>
<label>یادداشت / توضیح بازپرداخت<textarea name="refund_note" minlength="5" maxlength="1000" required></textarea></label>
<button class="button button-dark" type="submit">ثبت بازپرداخت انجام‌شده</button>
</form>
@endif
@if(in_array($order->status,['paid_stock_issue','refund_required','cancelled'],true))
<p class="notice-error">@if($order->status==='paid_stock_issue')تا زمان رفع مشکل موجودی، تغییر وضعیت سفارش غیرفعال است.@elseif($order->status==='refund_required')تا زمان ثبت بازپرداخت، تغییر وضعیت سفارش غیرفعال است.@else این سفارش لغو شده و امکان فعال‌سازی مجدد آن وجود ندارد.@endif</p>
@else
<form method="POST" action="{{ route('admin.orders.update',$order) }}" class="order-status-form">@csrf @method('PATCH')
<label>شرکت یا روش ارسال<input name="shipping_carrier" value="{{ old('shipping_carrier',$order->shipping_carrier) }}" maxlength="100" placeholder="پست، تیپاکس، پیک فروشگاه..."></label>
<label>کد رهگیری مرسوله<input name="tracking_code" value="{{ old('tracking_code',$order->tracking_code) }}" maxlength="120"></label>
<label>وضعیت سفارش<select name="status">
@php
$statusOptions=match($order->status){
'pending'=>['pending'=>'در انتظار','processing'=>'در حال آماده‌سازی']+($order->payment_status==='paid'?[]:['cancelled'=>'لغوشده']),
'processing'=>['processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده'],
'shipped'=>['shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده'],
'delivered'=>['delivered'=>'تحویل‌شده'],
default=>[],
};
@endphp
@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected($order->status===$value)>{{ $label }}</option>@endforeach
</select></label><button class="button button-dark">ذخیره وضعیت</button></form>
@endif
</div></section>
@endsection