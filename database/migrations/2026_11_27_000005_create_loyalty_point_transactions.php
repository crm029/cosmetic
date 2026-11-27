<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('loyalty_points')->default(0)->after('is_admin');
        });

        Schema::create('loyalty_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('points');
            $table->string('description', 255);
            $table->timestamps();
            $table->unique(['order_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_transactions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('loyalty_points');
        });
    }
};
