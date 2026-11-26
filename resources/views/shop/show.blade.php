@extends('layouts.app')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Product','name'=>$product->name,'description'=>$product->short_description ?: $product->description,'image'=>$product->image ? [asset('storage/'.$product->image)] : [],'sku'=>$product->sku,'offers'=>['@type'=>'Offer','priceCurrency'=>'IRR','price'=>$product->price*10,'availability'=>$product->stock>0?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>url()->current()]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<section class="section-block product-detail"><div class="detail-image">@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">@else<div class="product-placeholder detail-placeholder" data-3d-placeholder="true"><span></span></div>@endif</div>
<div class="detail-copy"><span class="eyebrow">{{ $product->category?->name ?? 'THE BEAUTY EDIT' }}</span><h1>{{ $product->name }}</h1><p>{{ $product->short_description }}</p><strong class="detail-price">{{ number_format($product->price) }} تومان</strong><p class="stock-note">{{ $product->stock>0?'موجود در فروشگاه':'در حال حاضر ناموجود' }}</p>
@if($product->stock>0)<form method="POST" action="{{ route('cart.add',$product) }}" class="add-cart-form">@csrf<label>تعداد<input type="number" name="quantity" min="1" max="{{ min(20,$product->stock) }}" value="1"></label><button class="button button-dark" type="submit">افزودن به سبد خرید <span>←</span></button></form>@else<button class="button button-dark" disabled>ناموجود</button>@endif
<div class="detail-description">{!! nl2br(e($product->description)) !!}</div></div></section>
@endsection