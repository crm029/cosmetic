<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTechnicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_canonical_url_must_match_the_current_host(): void
    {
        $product = Product::create([
            'name' => 'محصول canonical',
            'slug' => 'canonical-product',
            'price' => 100000,
            'stock' => 2,
            'is_active' => true,
            'canonical_url' => 'https://attacker.example/other-page',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.e(route('products.show', $product)).'">', false)
            ->assertSee('<meta property="og:type" content="product">', false);
    }

    public function test_same_host_product_canonical_url_is_preserved(): void
    {
        $product = Product::create([
            'name' => 'محصول canonical داخلی',
            'slug' => 'internal-canonical-product',
            'price' => 100000,
            'stock' => 2,
            'is_active' => true,
            'canonical_url' => 'http://localhost/product/preferred-canonical',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/product/preferred-canonical">', false);
    }

    public function test_second_catalog_page_has_a_self_referencing_canonical(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            Product::create([
                'name' => 'محصول صفحه '.$i,
                'slug' => 'catalog-page-product-'.$i,
                'price' => 10000 + $i,
                'stock' => 5,
                'is_active' => true,
            ]);
        }

        $this->get('/shop?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.e(url('/shop?page=2')).'">', false)
            ->assertSee('content="index,follow"', false);
    }

    public function test_robots_txt_does_not_block_noindex_storefront_pages(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin', false)
            ->assertDontSee('Disallow: /cart', false)
            ->assertDontSee('Disallow: /checkout', false)
            ->assertSee('/sitemap.xml', false);
    }
    public function test_category_page_exposes_a_product_item_list_and_preserves_pagination_parameters(): void
    {
        $category = Category::create([
            'name' => 'مراقبت پوست',
            'slug' => 'skin-care',
            'description' => 'محصولات مراقبت پوست',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 13; $i++) {
            Product::create([
                'category_id' => $category->id,
                'name' => 'محصول دسته '.$i,
                'slug' => 'category-product-'.$i,
                'price' => 50000 + $i,
                'stock' => 3,
                'is_active' => true,
            ]);
        }

        $this->get(route('categories.show', $category).'?page=2&campaign=spring')
            ->assertOk()
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('"numberOfItems":1', false)
            ->assertSee('محصول دسته 13', false)
            ->assertSee('page=2&amp;campaign=spring', false);
    }

}
