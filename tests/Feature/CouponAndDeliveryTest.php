<?php
namespace Tests\Feature;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CouponAndDeliveryTest extends TestCase {
 use RefreshDatabase;
 private function product(): Product {
  return Product::create(['name'=>'سرم تست تخفیف','slug'=>'coupon-test-serum','price'=>200000,'stock'=>5,'is_active'=>true]);
 }
 public function test_customer_can_apply_valid_coupon_and_checkout_shows_discount_and_pickup(): void {
  $product=$this->product();
  Coupon::create(['code'=>'WELCOME20','type'=>'percent','value'=>20,'minimum_order_toman'=>100000,'maximum_discount_toman'=>30000,'is_active'=>true]);
  StoreSetting::current()->update(['shipping_fee_toman'=>25000,'free_shipping_threshold_toman'=>500000,'address'=>'تهران، خیابان تست']);
  $this->withSession(['cart'=>[$product->id=>1]])
   ->from(route('checkout.index'))
   ->post(route('coupon.apply'),['code'=>'welcome20'])
   ->assertRedirect(route('checkout.index'))
   ->assertSessionHas('coupon_code','WELCOME20');
  $this->get(route('checkout.index',['shipping_method'=>'pickup']))
   ->assertOk()->assertSee('تحویل حضوری از فروشگاه')
   ->assertSee('WELCOME20')->assertSee('30,000')
   ->assertSee('تهران، خیابان تست');
 }
 public function test_coupon_rejects_expired_and_overused_codes(): void {
  $this->product();
  Coupon::create(['code'=>'EXPIRED10','type'=>'fixed','value'=>10000,'expires_at'=>now()->subDay(),'is_active'=>true]);
  Coupon::create(['code'=>'LIMITED10','type'=>'fixed','value'=>10000,'usage_limit'=>1,'used_count'=>1,'is_active'=>true]);
  $this->withSession(['cart'=>[1=>1]])->from(route('checkout.index'))->post(route('coupon.apply'),['code'=>'EXPIRED10'])->assertSessionHasErrors('coupon');
  $this->withSession(['cart'=>[1=>1]])->from(route('checkout.index'))->post(route('coupon.apply'),['code'=>'LIMITED10'])->assertSessionHasErrors('coupon');
 }
 public function test_admin_can_create_and_disable_coupon(): void {
  $admin=User::create(['name'=>'مدیر کوپن','mobile'=>'09123456780','password'=>'a-secure-test-password','is_admin'=>true]);
  $this->actingAs($admin)->post(route('admin.coupons.store'),[
   'code'=>'SPRING15','type'=>'percent','value'=>15,'minimum_order_toman'=>0,'usage_limit'=>100,'is_active'=>1,
  ])->assertRedirect()->assertSessionHasNoErrors();
  $coupon=Coupon::where('code','SPRING15')->firstOrFail();
  $this->actingAs($admin)->patch(route('admin.coupons.toggle',$coupon))->assertRedirect();
  $this->assertFalse($coupon->fresh()->is_active);
 }
}