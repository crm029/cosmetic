<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
    public function up(): void
    {
        // Order status is a string column; this migration documents the new
        // refund_required state without requiring a destructive schema change.
    }

    public function down(): void
    {
        // Existing orders may use this state, so intentionally do not rewrite them.
    }
};
