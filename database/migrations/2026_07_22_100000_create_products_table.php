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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ProductName');
            $table->unsignedBigInteger('SupplierID')->nullable();
            $table->unsignedBigInteger('CategoryId')->nullable();
            $table->string('QuantityPerUnit')->nullable();
            $table->decimal('UnitPrice', 10, 2)->default(0);
            $table->unsignedInteger('UnitStock')->default(0);
            $table->unsignedInteger('UnitsOnOrder')->default(0);
            $table->unsignedInteger('ReorderLevel')->default(0);
            $table->boolean('Discontinued')->default(false);
            $table->timestamps();

            $table->index('SupplierID');
            $table->index('CategoryId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
