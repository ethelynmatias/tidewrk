<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('step'); // inventory | payment | shipping | ...

            $table->enum('status', [
                'pending',
                'running',
                'succeeded',
                'failed',
                'skipped',
                'compensated',
            ])->default('pending');

            $table->unsignedInteger('attempt')->default(1);

            // Deterministic key ("{order_id}:{step}") — a unique constraint makes
            // a double-dispatched step impossible to record twice as succeeded.
            $table->string('idempotency_key')->unique();

            $table->string('error_class')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable(); // arbitrary debugging context

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'step']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_steps');
    }
};
