@extends('layouts.app')
@section('content')
<section class="admin-wrap">
    <div class="admin-top">
        <div><span class="eyebrow">CUSTOMER PROFILE</span><h1>{{ $customerName }}</h1><p>{{ $mobile }} @if($email) — {{ $email }} @endif</p></div>
        <a class="text-link" href="{{ route('admin.customers') }}">بازگشت به مشتریان ←</a>
    </div>
    <div class="admin-stats customer-detail-stats">
        <div><span>تعداد کل سفارش‌ها</span><b>{{ number_format($orderCount) }}</b></div>
        <div><span>مجموع خرید پرداخت‌شده (تومان)</span><b>{{ number_format($paidTotal) }}</b></div>
    </div>
    <div class="admin-panel">
        <h2>تاریخچه سفارش‌های مشتری</h2>
        <div class="admin-table">
            <div class="admin-row order-admin-head"><span>شماره سفارش</span><span>تاریخ</span><span>مبلغ</span><span>پرداخت</span><span>وضعیت</span><span>جزئیات</span></div>
            @forelse($orders as $order)
                <div class="admin-row order-admin-row">
                    <span>{{ $order->order_number }}</span>
                    <span>{{ $order->created_at->format('Y/m/d') }}</span>
                    <span>{{ number_format($order->total) }} تومان</span>
                    <span>{{ ['paid'=>'پرداخت‌شده','unpaid'=>'پرداخت‌نشده','failed'=>'ناموفق','refunded'=>'بازپرداخت‌شده'][$order->payment_status] ?? $order->payment_status }}</span>
                    <span>{{ ['pending'=>'در انتظار','processing'=>'آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده','paid_stock_issue'=>'مشکل موجودی','refund_required'=>'نیازمند بازپرداخت'][$order->status] ?? $order->status }}</span>
                    <span><a href="{{ route('admin.orders.show',$order) }}">مشاهده سفارش ←</a></span>
                </div>
            @empty
                <p>سفارشی برای این مشتری ثبت نشده است.</p>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $orders->links() }}</div>
    </div>
</section>
@endsection
