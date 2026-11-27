@extends('layouts.app')
@section('content')
<section class="admin-wrap">
    <div class="admin-top">
        <div><span class="eyebrow">SALES INTELLIGENCE</span><h1>گزارش فروش</h1><p>آمار سفارش‌ها و فروش بر اساس تاریخ ثبت سفارش</p></div>
        <a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a>
    </div>

    @if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="admin-filter report-filter" method="GET" action="{{ route('admin.reports') }}">
        <label>از تاریخ <input type="date" name="from" value="{{ $startDate }}"></label>
        <label>تا تاریخ <input type="date" name="to" value="{{ $endDate }}"></label>
        <button class="button button-dark">اعمال بازه</button>
        <a class="text-link" href="{{ route('admin.reports') }}">۳۰ روز اخیر</a>
    </form>

    <div class="admin-stats report-stats">
        <div><span>مجموع فروش پرداخت‌شده (تومان)</span><b>{{ number_format($paidRevenue) }}</b></div>
        <div><span>تعداد سفارش‌ها</span><b>{{ number_format($orderCount) }}</b></div>
        <div><span>سفارش‌های پرداخت‌شده</span><b>{{ number_format($paidOrderCount) }}</b></div>
        <div><span>میانگین مبلغ سفارش پرداخت‌شده</span><b>{{ number_format($averageOrderValue) }}</b></div>
        <div><span>سفارش‌های پرداخت‌نشده</span><b>{{ number_format($unpaidOrderCount) }}</b></div>
        <div><span>مبلغ سفارش‌های بازپرداخت‌شده (تومان)</span><b>{{ number_format($refundedTotal) }}</b></div>
    </div>

    <div class="admin-panel">
        <div class="section-heading"><div><span class="eyebrow">DAILY PERFORMANCE</span><h2>روند فروش روزانه</h2></div><small>{{ $startDate }} تا {{ $endDate }}</small></div>
        <div class="report-chart-scroll">
            <div class="report-chart">
                @foreach($dailySales as $day)
                    <div class="report-chart-column" title="{{ $day['date'] }} | {{ number_format($day['revenue']) }} تومان | {{ $day['orders'] }} سفارش">
                        <strong>{{ $day['revenue'] ? number_format($day['revenue']) : '—' }}</strong>
                        <div class="report-chart-track"><span class="report-chart-bar" style="height: {{ max(3, round(($day['revenue'] / $maxDailyRevenue) * 100)) }}%"></span></div>
                        <small>{{ $day['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="report-tables">
        <div class="admin-panel">
            <h2>پرفروش‌ترین محصولات</h2>
            <div class="admin-table">
                <div class="admin-row report-product-head"><span>محصول</span><span>تعداد فروخته‌شده</span><span>فروش (تومان)</span></div>
                @forelse($topProducts as $product)
                    <div class="admin-row report-product-row"><span>{{ $product->product_name }}</span><span>{{ number_format($product->quantity) }}</span><span>{{ number_format($product->revenue) }}</span></div>
                @empty
                    <p>در این بازه فروش پرداخت‌شده‌ای ثبت نشده است.</p>
                @endforelse
            </div>
        </div>
        <div class="admin-panel">
            <h2>مشتریان برتر</h2>
            <div class="admin-table">
                <div class="admin-row report-customer-head"><span>مشتری</span><span>موبایل</span><span>سفارش</span><span>مجموع خرید</span></div>
                @forelse($topCustomers as $customer)
                    <a class="admin-row report-customer-row" href="{{ route('admin.customers.show',$customer->mobile) }}"><span>{{ $customer->customer_name }}</span><span>{{ $customer->mobile }}</span><span>{{ number_format($customer->orders_count) }}</span><span>{{ number_format($customer->paid_total) }}</span></a>
                @empty
                    <p>مشتری پرداخت‌کننده‌ای در این بازه وجود ندارد.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
