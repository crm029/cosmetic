<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('orders',function(Blueprint $table){
  $table->id();$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$table->string('order_number')->unique();$table->string('customer_name');$table->string('mobile',20);$table->string('email')->nullable();$table->text('shipping_address');$table->unsignedBigInteger('subtotal');$table->unsignedBigInteger('shipping_amount')->default(0);$table->unsignedBigInteger('total');$table->string('status')->default('pending')->index();$table->string('payment_status')->default('unpaid')->index();$table->string('payment_authority')->nullable()->index();$table->string('payment_ref_id')->nullable();$table->text('notes')->nullable();$table->timestamps();
 });}
 public function down(): void { Schema::dropIfExists('orders'); }
};