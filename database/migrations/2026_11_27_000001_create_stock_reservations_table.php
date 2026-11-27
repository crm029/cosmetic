<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['product_id', 'expires_at', 'released_at', 'consumed_at'], 'stock_reservations_active_lookup');
            $table->index(['order_id', 'product_id'], 'stock_reservations_order_product_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
