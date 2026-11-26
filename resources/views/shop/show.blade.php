@extends('layouts.app')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Product','name'=>$product->name,'description'=>$product->short_description ?: $product->description,'image'=>$product->image ? [asset('storage/'.$product->image)] : [],'sku'=>$product->sku,'offers'=>['@type'=>'Offer','priceCurrency'=>'IRR','price'=>$product->price,'availability'=>$product->stock>0?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>url()->current()]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@if($product->canonical_url)<link rel="canonical" href="{{ $product->canonical_url }}">@endif
@endpush
@section('content')
<section class="section-block product-detail"><div class="detail-image">@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">@else<div class="product-placeholder detail-placeholder"><span></span></div>@endif</div>
<div class="detail-copy"><span class="eyebrow">{{ $product->category?->name ?? 'THE BEAUTY EDIT' }}</span><h1>{{ $product->name }}</h1><p>{{ $product->short_description }}</p><strong class="detail-price">{{ number_format($product->price) }} تومان</strong><p class="stock-note">{{ $product->stock>0?'موجود در فروشگاه':'در حال حاضر ناموجود' }}</p><button class="button button-dark" disabled>افزودن به سبد خرید <span>←</span></button><div class="detail-description">{!! nl2br(e($product->description)) !!}</div></div></section>
@endsection