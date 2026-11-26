<div class="product-price-line">
    @if($product->compare_price && $product->compare_price > $product->price)
        <del>{{ number_format($product->compare_price) }}</del>
        <span class="sale-badge">پیشنهاد ویژه</span>
    @endif
    <strong>{{ number_format($product->price) }} <small>تومان</small></strong>
    @if($product->stock < 1)
        <span class="stock-badge">ناموجود</span>
    @endif
</div>