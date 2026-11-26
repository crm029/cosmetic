<?php
namespace Tests\Feature;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HomePageTest extends TestCase {
 use RefreshDatabase;
 public function test_homepage_returns_successful_response(): void { $this->get('/')->assertOk()->assertSee('زیبایی'); }
 public function test_shop_page_returns_successful_response(): void { $this->get('/shop')->assertOk(); }
 public function test_admin_is_protected_from_guests(): void { $this->get('/admin')->assertRedirect('/admin/login'); }
 public function test_customer_can_add_an_available_product_to_cart(): void {
  $product=Product::create(['name'=>'نمونه محصول','slug'=>'sample-product','price'=>120000,'stock'=>5,'is_active'=>true]);
  $this->post('/cart/'.$product->id,['quantity'=>2])->assertRedirect('/cart');
  $this->get('/cart')->assertOk()->assertSee('نمونه محصول')->assertSee('240,000');
 }
}