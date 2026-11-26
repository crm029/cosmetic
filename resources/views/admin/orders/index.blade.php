@extends('layouts.app')
@section('content')
<section class="admin-wrap"><div class="admin-top"><div><span class="eyebrow">ORDER MANAGEMENT</span><h1>سفارش‌ها</h1></div><a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a></div>
<div class="admin-panel"><div class="admin-table"><div class="admin-row order-admin-head"><span>شماره</span><span>مشتری</span><span>موبایل</span><span>مبلغ</span><span>پرداخت</span><span>وضعیت</span></div>
@forelse($orders as $order)<a class="admin-row order-admin-row" href="{{ route('admin.orders.show',$order) }}"><span>{{ $order->order_number }}</span><span>{{ $order->customer_name }}</span><span>{{ $order->mobile }}</span><span>{{ number_format($order->total) }}</span><span>{{ $order->payment_status==='paid'?'پرداخت‌شده':'پرداخت‌نشده' }}</span><span>{{ $order->status }}</span></a>@empty<p>هنوز سفارشی ثبت نشده است.</p>@endforelse</div><div class="pagination-wrap">{{ $orders->links() }}</div></div></section>
@endsection