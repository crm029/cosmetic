@extends('layouts.app')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$category->name,'description'=>$category->seo_description ?: $category->description,'url'=>url()->current()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<section class="page-intro"><span class="eyebrow">THE COLLECTION</span><h1>{{ $category->name }}</h1><p>{{ $category->description ?: 'انتخاب‌هایی برای روتین مراقبت روزانه تو.' }}</p>@if($category->image)
<img class="category-hero-image" src="{{ asset('storage/'.$category->image) }}" alt="{{ $category->name }}" loading="lazy">
@endif</section>
<section class="section-block"><div class="section-heading"><div><span class="eyebrow">CURATED FOR YOU</span><h2>محصولات این دسته</h2></div><a class="text-link" href="{{ route('shop') }}">بازگشت به فروشگاه ←</a></div>
<div class="product-grid">@forelse($products as $product)<a class="product-card" href="{{ route('products.show',$product) }}"><div class="product-image">@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" loading="lazy">@else<div class="product-placeholder"><span></span></div>@endif</div><div class="product-meta"><span>{{ $category->name }}</span><span>♡</span></div><h3>{{ $product->name }}</h3><strong>{{ number_format($product->price) }} <small>تومان</small></strong></a>@empty<p class="empty-products">هنوز محصولی در این دسته ثبت نشده است.</p>@endforelse</div>
<div class="pagination-wrap">{{ $products->links() }}</div></section>
@endsection