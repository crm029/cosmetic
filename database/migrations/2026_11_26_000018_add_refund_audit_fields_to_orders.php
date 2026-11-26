<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_ref_id')->nullable()->after('payment_ref_id');
            $table->text('refund_note')->nullable()->after('refund_ref_id');
            $table->timestamp('refunded_at')->nullable()->after('refund_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_ref_id', 'refund_note', 'refunded_at']);
        });
    }
};
