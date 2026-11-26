@extends('layouts.app')

@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<section class="section-block product-detail">
    <div class="detail-image">
        @if($product->model_3d)
            <div class="product-3d-viewer" role="region" aria-label="نمایش سه‌بعدی محصول؛ برای چرخاندن لمس یا ماوس را حرکت دهید" data-model-url="{{ asset('storage/'.$product->model_3d) }}">
                @if($product->image)
                    <img class="product-3d-fallback" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
                @else
                    <div class="product-3d-fallback product-placeholder detail-placeholder"><span></span></div>
                @endif
            </div>
        @else
            @if($product->image)
                <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="product-placeholder detail-placeholder" data-3d-placeholder="true"><span></span></div>
            @endif
        @endif

        @if($product->gallery)
            <div class="product-gallery">
                @foreach($product->gallery as $image)
                    <img src="{{ asset('storage/'.$image) }}" alt="{{ $product->name }} - تصویر {{ $loop->iteration }}" loading="lazy">
                @endforeach
            </div>
        @endif
    </div>

    <div class="detail-copy">
        <span class="eyebrow">{{ $product->category?->name ?? 'THE BEAUTY EDIT' }}</span>
        <h1>{{ $product->name }}</h1>
        <p>{{ $product->short_description }}</p>
        <div class="product-detail-price">
            @if($product->compare_price && $product->compare_price > $product->price)
                <del>{{ number_format($product->compare_price) }} تومان</del>
                <span class="sale-badge">پیشنهاد ویژه</span>
            @endif
            <strong class="detail-price">{{ number_format($product->price) }} تومان</strong>
        </div>
        <p class="stock-note">{{ $product->stock > 0 ? 'موجود در فروشگاه' : 'در حال حاضر ناموجود' }}</p>

        @if($product->stock > 0)
            <form method="POST" action="{{ route('cart.add',$product) }}" class="add-cart-form">
                @csrf
                <label>تعداد
                    <input type="number" name="quantity" min="1" max="{{ min(20,$product->stock) }}" value="1">
                </label>
                <button class="button button-dark" type="submit">افزودن به سبد خرید <span>←</span></button>
            </form>
        @else
            <button class="button button-dark" disabled>ناموجود</button>
        @endif

        <div class="detail-description">{!! nl2br(e($product->description)) !!}</div>
    </div>
</section>

@if($relatedProducts->isNotEmpty())
<section class="section-block related-products">
    <div class="section-heading">
        <div><span class="eyebrow">YOU MAY ALSO LIKE</span><h2>شاید این‌ها را هم دوست داشته باشی</h2></div>
        <a class="text-link" href="{{ route('shop') }}">مشاهده همه محصولات ←</a>
    </div>
    <div class="product-grid">
        @foreach($relatedProducts as $relatedProduct)
            <a class="product-card" href="{{ route('products.show',$relatedProduct) }}">
                <div class="product-image">
                    @if($relatedProduct->image)
                        <img src="{{ asset('storage/'.$relatedProduct->image) }}" alt="{{ $relatedProduct->name }}" loading="lazy">
                    @else
                        <div class="product-placeholder"><span></span></div>
                    @endif
                </div>
                <div class="product-meta"><span>{{ $relatedProduct->category?->name ?? 'THE BEAUTY EDIT' }}</span></div>
                <h3>{{ $relatedProduct->name }}</h3>
                @include('components.product-price',['product'=>$relatedProduct])
            </a>
        @endforeach
    </div>
</section>
@endif
@endsection
