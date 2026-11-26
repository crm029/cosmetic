@extends('layouts.app')
@section('content')
<section class="admin-wrap">
<div class="admin-top"><div><span class="eyebrow">INVENTORY AUDIT</span><h1>تاریخچه موجودی</h1><p>{{ $product->name }} — موجودی فعلی: {{ number_format($product->stock) }}</p></div><a class="text-link" href="{{ route('admin.products.edit',$product) }}">ویرایش محصول ←</a></div>
<div class="admin-panel"><div class="admin-table">
<div class="admin-row stock-history-head"><span>زمان</span><span>نوع تغییر</span><span>تغییر</span><span>قبل / بعد</span><span>ثبت‌کننده / سفارش</span><span>توضیحات</span></div>
@forelse($movements as $movement)
<div class="admin-row stock-history-row">
<span>{{ $movement->created_at->format('Y/m/d H:i') }}</span>
<span>{{ ['initial'=>'موجودی اولیه','adjustment'=>'اصلاح دستی','sale'=>'فروش'][$movement->type] ?? $movement->type }}</span>
<span class="{{ $movement->quantity<0?'stock-negative':'stock-positive' }}">{{ $movement->quantity>0?'+':'' }}{{ number_format($movement->quantity) }}</span>
<span>{{ number_format($movement->previous_stock) }} ← {{ number_format($movement->new_stock) }}</span>
<span>{{ $movement->admin?->name ?: ($movement->order?->order_number ?: 'سیستم') }}</span>
<span>{{ $movement->note ?: '—' }}</span>
</div>
@empty
<p>هنوز تغییری برای موجودی این محصول ثبت نشده است.</p>
@endforelse
</div><div class="pagination-wrap">{{ $movements->links() }}</div></div>
</section>
@endsection