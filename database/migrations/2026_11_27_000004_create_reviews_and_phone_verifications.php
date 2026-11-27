<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('product_reviews',function(Blueprint $table){
   $table->id();$table->foreignId('product_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();$table->unsignedTinyInteger('rating');$table->string('title',120)->nullable();$table->text('body');
   $table->string('status',20)->default('pending')->index();$table->timestamps();$table->unique(['product_id','user_id']);
  });
  Schema::create('phone_verifications',function(Blueprint $table){
   $table->id();$table->string('mobile',20)->index();$table->string('code_hash');$table->timestamp('expires_at');$table->unsignedTinyInteger('attempts')->default(0);$table->timestamp('consumed_at')->nullable();$table->timestamps();$table->index(['mobile','consumed_at']);
  });
 }
 public function down(): void {Schema::dropIfExists('phone_verifications');Schema::dropIfExists('product_reviews');}
};