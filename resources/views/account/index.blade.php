@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">MY ACCOUNT</span><h1>سلام، <em>{{ auth()->user()->name }}</em></h1><p>سفارش‌های ثبت‌شده با شماره {{ auth()->user()->mobile }} در این حساب.</p><form method="POST" action="{{ route('account.logout') }}">@csrf<button class="text-link">خروج از حساب</button></form></section>
<section class="section-block">
    <div class="admin-stats customer-detail-stats">
        <div><span>امتیاز وفاداری شما</span><b>{{ number_format(auth()->user()->loyalty_points) }}</b></div>
        <div><span>روش کسب امتیاز</span><b>هر ۱۰٬۰۰۰ تومان خرید خالص = ۱ امتیاز</b></div>
    </div>
    <div class="admin-panel">
        <h2>تاریخچه امتیازها</h2>
        @forelse($transactions as $transaction)
            <div class="account-order"><strong>{{ $transaction->description }}</strong><span>+{{ number_format($transaction->points) }} امتیاز</span><small>{{ $transaction->created_at->format('Y/m/d H:i') }}</small></div>
        @empty
            <p>هنوز امتیازی برای این حساب ثبت نشده است. پس از پرداخت و تکمیل موفق سفارش، امتیاز خرید به‌صورت خودکار اضافه می‌شود.</p>
        @endforelse
    </div>
</section>
<section class="section-block"><div class="admin-panel"><h2>سفارش‌های من</h2>@forelse($orders as $order)<div class="account-order"><strong>{{ $order->order_number }}</strong><span>{{ number_format($order->total) }} تومان</span><span>{{ ['pending'=>'در انتظار پرداخت','processing'=>'در حال آماده‌سازی','shipped'=>'ارسال‌شده','delivered'=>'تحویل‌شده','cancelled'=>'لغوشده','paid_stock_issue'=>'نیازمند بررسی','refund_required'=>'در انتظار بازپرداخت'][$order->status]??$order->status }}</span><a href="{{ route('orders.track') }}">پیگیری سفارش</a></div>@empty<p>هنوز سفارشی برای این حساب ثبت نشده است.</p>@endforelse{{ $orders->links() }}</div></section>
@endsection
