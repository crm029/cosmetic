<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('store_settings',function(Blueprint $table){
  $table->id();$table->string('store_name',150)->default('Cosmetic Store');$table->string('tagline',255)->nullable();$table->unsignedBigInteger('shipping_fee_toman')->default(0);$table->unsignedBigInteger('free_shipping_threshold_toman')->default(0);$table->string('support_mobile',20)->nullable();$table->string('support_email',190)->nullable();$table->text('address')->nullable();$table->string('ink_color',7)->default('#29251f');$table->string('accent_color',7)->default('#a77d6a');$table->timestamps();
 });}
 public function down(): void { Schema::dropIfExists('store_settings'); }
};