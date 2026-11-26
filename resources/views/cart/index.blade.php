@extends('layouts.app')
@section('content')
<section class="page-intro"><span class="eyebrow">YOUR SELECTION</span><h1>سبد <em>خرید</em></h1></section>
<section class="section-block cart-page">
@if(session('success'))<p class="notice-success">{{ session('success') }}</p>@endif
@if($errors->any())<p class="notice-error">{{ $errors->first() }}</p>@endif
@if(count($lines))
<div class="cart-lines">@foreach($lines as $line)<article class="cart-line"><div class="cart-thumb">@if($line['product']->image)<img src="{{ asset('storage/'.$line['product']->image) }}" alt="{{ $line['product']->name }}">@else<div class="product-placeholder"><span></span></div>@endif</div><div class="cart-line-info"><h3>{{ $line['product']->name }}</h3><strong>{{ number_format($line['product']->price) }} تومان</strong><form method="POST" action="{{ route('cart.update',$line['product']) }}">@csrf @method('PATCH')<input type="number" name="quantity" min="1" max="20" value="{{ $line['quantity'] }}"><button>به‌روزرسانی</button></form><form method="POST" action="{{ route('cart.remove',$line['product']) }}">@csrf @method('DELETE')<button class="remove-link">حذف</button></form></div><b>{{ number_format($line['line_total']) }} تومان</b></article>@endforeach</div>
<div class="cart-summary"><span>جمع محصولات</span><strong>{{ number_format($total) }} تومان</strong><a class="button button-dark" href="{{ route('checkout.index') }}">ادامه و ثبت سفارش ←</a></div>
@else<p class="empty-products">سبد خریدت هنوز خالی است.</p><a class="button button-dark" href="{{ route('shop') }}">رفتن به فروشگاه ←</a>@endif
</section>
@endsection