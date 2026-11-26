<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('orders',function(Blueprint $table){
  $table->string('shipping_carrier',100)->nullable();$table->string('tracking_code',120)->nullable();$table->timestamp('shipped_at')->nullable();$table->timestamp('delivered_at')->nullable();
 });}
 public function down(): void { Schema::table('orders',function(Blueprint $table){$table->dropColumn(['shipping_carrier','tracking_code','shipped_at','delivered_at']);}); }
};