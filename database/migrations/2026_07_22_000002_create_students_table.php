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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Each student is linked to exactly one school.
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('student_id'); // external id, e.g. 10001
            $table->string('student_code');           // natural key, e.g. STU-001
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->timestamps();

            // No duplicate students — enforced at the DB level.
            $table->unique('student_code');
            $table->unique('student_id');

            // Fast lookups / joins by school.
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
