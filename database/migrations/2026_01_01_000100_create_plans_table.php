<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plan limits are stored as plain integers so the same schema works on
     * MySQL and SQLite. The Stripe price identifier is reserved for the
     * billing phase and is nullable until then.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_price_id')->nullable();
            $table->unsignedInteger('max_spaces')->default(3);
            $table->unsignedInteger('max_testimonials_per_space')->default(100);
            $table->unsignedInteger('max_testimonials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
