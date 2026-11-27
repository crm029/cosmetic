@extends('layouts.app')
@section('content')
<section class="admin-wrap"><div class="admin-top"><div><span class="eyebrow">PROMOTION MANAGEMENT</span><h1>کدهای تخفیف</h1></div><a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a></div>
@if(session('success'))<p class="notice-success">{{ session('success') }}</p>@endif
@if($errors->any())<div class="notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="admin-panel"><h2>ساخت کد تخفیف</h2><form method="POST" action="{{ route('admin.coupons.store') }}" class="checkout-form">@csrf
<label>کد تخفیف<input name="code" required maxlength="40" pattern="[A-Za-z0-9_-]+" placeholder="WELCOME10"></label>
<label>نوع تخفیف<select name="type"><option value="percent">درصدی</option><option value="fixed">مبلغ ثابت (تومان)</option></select></label>
<label>مقدار تخفیف<input type="number" name="value" min="1" required></label>
<label>حداقل مبلغ خرید (تومان)<input type="number" name="minimum_order_toman" min="0" value="0" required></label>
<label>سقف تخفیف (تومان، اختیاری)<input type="number" name="maximum_discount_toman" min="1"></label>
<label>حداکثر دفعات استفاده (اختیاری)<input type="number" name="usage_limit" min="1"></label>
<label>شروع اعتبار<input type="datetime-local" name="starts_at"></label><label>پایان اعتبار<input type="datetime-local" name="expires_at"></label>
<label><input type="checkbox" name="is_active" value="1" checked> فعال باشد</label><button class="button button-dark">ثبت کد تخفیف</button></form></div>
<div class="admin-panel"><h2>کدهای ثبت‌شده</h2><div class="admin-table"><div class="admin-row admin-head"><span>کد</span><span>تخفیف</span><span>استفاده</span><span>اعتبار</span><span>وضعیت</span><span>عملیات</span></div>
@forelse($coupons as $coupon)<div class="admin-row"><span>{{ $coupon->code }}</span><span>{{ $coupon->type==='percent'?$coupon->value.'٪':number_format($coupon->value).' تومان' }}</span><span>{{ $coupon->used_count }} / {{ $coupon->usage_limit??'∞' }}</span><span>{{ $coupon->expires_at?->format('Y/m/d H:i')??'بدون انقضا' }}</span><span>{{ $coupon->is_active?'فعال':'غیرفعال' }}</span><span><form method="POST" action="{{ route('admin.coupons.toggle',$coupon) }}">@csrf @method('PATCH')<button>{{ $coupon->is_active?'غیرفعال‌سازی':'فعال‌سازی' }}</button></form></span></div>@empty<p>هنوز کدی ثبت نشده است.</p>@endforelse</div>{{ $coupons->links() }}</div></section>
@endsection