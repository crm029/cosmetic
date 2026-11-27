<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('coupons',function(Blueprint $table){
   $table->id();$table->string('code',40)->unique();$table->string('type',20)->default('fixed');
   $table->unsignedBigInteger('value');$table->unsignedBigInteger('minimum_order_toman')->default(0);
   $table->unsignedBigInteger('maximum_discount_toman')->nullable();$table->unsignedInteger('usage_limit')->nullable();
   $table->unsignedInteger('used_count')->default(0);$table->timestamp('starts_at')->nullable();$table->timestamp('expires_at')->nullable();
   $table->boolean('is_active')->default(true)->index();$table->timestamps();
  });
  Schema::table('orders',function(Blueprint $table){
   $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons')->nullOnDelete();
   $table->string('coupon_code',40)->nullable()->after('shipping_amount');
   $table->unsignedBigInteger('discount_amount')->default(0)->after('coupon_code');
   $table->string('shipping_method',20)->default('post')->after('shipping_address');
  });
 }
 public function down(): void {
  Schema::table('orders',function(Blueprint $table){
   $table->dropConstrainedForeignId('coupon_id');$table->dropColumn(['coupon_code','discount_amount','shipping_method']);
  });
  Schema::dropIfExists('coupons');
 }
};