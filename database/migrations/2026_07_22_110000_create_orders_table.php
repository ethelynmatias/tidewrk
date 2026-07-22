<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // natural / idempotency key

            $table->enum('status', [
                'pending',
                'confirmed',
                'partially_failed',
                'completed',
                'cancelled',
            ])->default('pending');

            $table->decimal('total_amount', 12, 2)->default(0);

            // Optimistic-locking token — bumped on every state change so a stale
            // worker's write affects zero rows and can be detected.
            $table->unsignedBigInteger('version')->default(0);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
