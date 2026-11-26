@extends('layouts.app')

@push('head')
@php
    $productImages = [];
    if ($product->image) {
        $productImages[] = asset('storage/'.$product->image);
    }
    foreach (($product->gallery ?? []) as $galleryImage) {
        $productImages[] = asset('storage/'.$galleryImage);
    }

    $productStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->short_description ?: $product->description,
        'image' => $productImages,
        'sku' => $product->sku,
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'IRR',
            'price' => $product->price * 10,
            'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => url()->current(),
        ],
    ];

    $breadcrumbStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'فروشگاه', 'item' => route('shop')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product->name, 'item' => url()->current()],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($productStructuredData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbStructuredData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<section class="section-block product-detail">
    <div class="detail-image">
        @if($product->model_3d)
            <div class="product-3d-viewer" role="region" aria-label="نمایش سه‌بعدی محصول؛ برای چرخاندن لمس یا ماوس را حرکت دهید" data-model-url="{{ asset('storage/'.$product->model_3d) }}"></div>
        @elseif($product->image)
            <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
        @else
            <div class="product-placeholder detail-placeholder" data-3d-placeholder="true"><span></span></div>
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
        <strong class="detail-price">{{ number_format($product->price) }} تومان</strong>
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
@endsection
