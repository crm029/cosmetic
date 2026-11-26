<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('products',function(Blueprint $table){
  $table->id();$table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();$table->string('name');$table->string('slug')->unique();$table->string('sku')->nullable()->unique();$table->string('short_description',500)->nullable();$table->longText('description')->nullable();$table->unsignedBigInteger('price');$table->unsignedBigInteger('compare_price')->nullable();$table->unsignedInteger('stock')->default(0);$table->string('image')->nullable();$table->json('gallery')->nullable();$table->boolean('is_active')->default(false)->index();$table->boolean('is_featured')->default(false)->index();$table->string('seo_title')->nullable();$table->string('seo_description',300)->nullable();$table->string('canonical_url')->nullable();$table->timestamps();$table->index(['is_active','is_featured']);
 });}
 public function down(): void { Schema::dropIfExists('products'); }
};