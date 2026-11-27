@extends('layouts.app')
@section('content')
<section class="admin-wrap">
    <div class="admin-top">
        <div><span class="eyebrow">CUSTOMER DIRECTORY</span><h1>مشتریان</h1><p>فهرست مشتریان بر اساس شماره موبایل سفارش‌ها</p></div>
        <a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a>
    </div>
    <form class="admin-filter" method="GET" action="{{ route('admin.customers') }}">
        <input name="q" value="{{ request('q') }}" placeholder="نام، شماره موبایل یا ایمیل مشتری">
        <button class="button button-dark">جستجو</button>
        <a class="text-link" href="{{ route('admin.customers') }}">پاک‌کردن</a>
    </form>
    <div class="admin-panel">
        <div class="admin-table">
            <div class="admin-row customer-admin-head"><span>نام مشتری</span><span>موبایل</span><span>تعداد سفارش</span><span>خرید پرداخت‌شده</span><span>آخرین سفارش</span></div>
            @forelse($customers as $customer)
                <a class="admin-row customer-admin-row" href="{{ route('admin.customers.show',$customer->mobile) }}">
                    <span>{{ $customer->customer_name }}</span>
                    <span>{{ $customer->mobile }}</span>
                    <span>{{ number_format($customer->orders_count) }}</span>
                    <span>{{ number_format($customer->paid_total) }} تومان</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('Y/m/d') }}</span>
                </a>
            @empty
                <p>مشتری‌ای با این مشخصات در سفارش‌ها پیدا نشد.</p>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $customers->links() }}</div>
    </div>
</section>
@endsection
