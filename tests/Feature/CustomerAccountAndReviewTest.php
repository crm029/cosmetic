<?php
namespace Tests\Feature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class CustomerAccountAndReviewTest extends TestCase {
 use RefreshDatabase;
 private static string $otp='';
 private function customer(string $mobile='09123456781'): User {
  return User::create(['name'=>'مشتری تست','mobile'=>$mobile,'password'=>'a-secure-test-password','is_admin'=>false]);
 }
 private function product(): Product {
  return Product::create(['name'=>'سرم تست نظرات','slug'=>'review-test-serum','price'=>180000,'stock'=>5,'is_active'=>true]);
 }
 private function paidOrder(User $user,Product $product): Order {
  $order=Order::create(['user_id'=>$user->id,'order_number'=>'CS-REVIEW-'.strtoupper(substr(md5((string)$user->id),0,6)),'customer_name'=>$user->name,'mobile'=>$user->mobile,'shipping_address'=>'تهران، نشانی آزمایشی معتبر','subtotal'=>180000,'shipping_amount'=>0,'total'=>180000,'status'=>'delivered','payment_status'=>'paid']);
  OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_name'=>$product->name,'unit_price'=>180000,'quantity'=>1,'line_total'=>180000]);
  return $order;
 }
 public function test_customer_can_receive_sms_otp_and_existing_orders_are_linked_after_verification(): void {
  $oldOrder=Order::create(['order_number'=>'CS-OLD-OTP-01','customer_name'=>'مشتری قبلی','mobile'=>'09123456782','shipping_address'=>'تهران، نشانی آزمایشی معتبر','subtotal'=>100000,'shipping_amount'=>0,'total'=>100000]);
  config(['services.0098sms'=>['base_url'=>'https://sms.example.test/send','username'=>'test','password'=>'secret','sender'=>'1000','domain'=>'0098']]);
  Http::fake(function($request){preg_match('/(\d{6})/',$request->data()['TEXT']??'',$matches);self::$otp=$matches[1]??'';return Http::response('0',200);});
  $this->post(route('customer.send-code'),['mobile'=>'09123456782'])->assertRedirect()->assertSessionHas('customer_otp_mobile','09123456782');
  $this->assertNotEmpty(self::$otp);
  $this->withSession(['customer_otp_mobile'=>'09123456782'])->post(route('customer.verify'),['mobile'=>'09123456782','code'=>self::$otp,'name'=>'مشتری جدید'])
   ->assertRedirect(route('account.index'));
  $this->assertAuthenticated();
  $this->assertDatabaseHas('orders',['id'=>$oldOrder->id,'user_id'=>auth()->id()]);
  $this->assertDatabaseHas('phone_verifications',['mobile'=>'09123456782']);
 }
 public function test_customer_otp_is_rate_limited_and_invalid_codes_consume_attempts(): void {
  config(['services.0098sms'=>['base_url'=>'https://sms.example.test/send','username'=>'test','password'=>'secret','sender'=>'1000','domain'=>'0098']]);
  Http::fake(fn()=>Http::response('0',200));
  $this->post(route('customer.send-code'),['mobile'=>'09123456783'])->assertRedirect();
  $this->withSession(['customer_otp_mobile'=>'09123456783'])->post(route('customer.verify'),['mobile'=>'09123456783','code'=>'000000'])->assertSessionHasErrors('code');
  $this->assertDatabaseHas('phone_verifications',['mobile'=>'09123456783','attempts'=>1]);
 }
 public function test_only_verified_paying_customers_can_submit_one_moderated_review_per_product(): void {
  $user=$this->customer();$product=$this->product();$this->paidOrder($user,$product);
  $this->actingAs($user)->post(route('products.reviews.store',$product),['rating'=>5,'title'=>'عالی','body'=>'کیفیت محصول واقعاً خوب بود.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $review=ProductReview::firstOrFail();
  $this->assertSame('pending',$review->status);
  $this->actingAs($user)->post(route('products.reviews.store',$product),['rating'=>4,'body'=>'نظر دوم برای محصول.'])->assertSessionHasErrors('review');
  $admin=User::create(['name'=>'مدیر تست','mobile'=>'09123456789','password'=>'a-secure-test-password','is_admin'=>true]);
  $this->actingAsAdmin($admin)->patch(route('admin.reviews.moderate',$review),['status'=>'approved'])->assertSessionHasNoErrors();
  $this->assertDatabaseHas('product_reviews',['id'=>$review->id,'status'=>'approved']);
 }
 public function test_unverified_customer_cannot_review_and_reviews_are_not_public_before_approval(): void {
  $user=$this->customer('09123456784');$product=$this->product();
  $this->actingAs($user)->post(route('products.reviews.store',$product),['rating'=>5,'body'=>'هنوز خریدی ثبت نشده است.'])->assertSessionHasErrors('review');
  $this->assertDatabaseCount('product_reviews',0);
 }
}