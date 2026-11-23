<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A widget belongs to a space and carries the settings that decide how
     * that space's testimonials are presented.
     *
     * The public identifier is a separate column rather than the primary
     * key, because the public URL must not expose an internal identifier.
     * It is unique, so two widgets can never share a public URL.
     *
     * The space is unique as well, because a space has one widget. That
     * rule is enforced by the database rather than by the application
     * alone, so two simultaneous requests cannot both create a widget for
     * the same space.
     *
     * There is no user column: ownership is reached through the space, so
     * a widget can never point at a different account than its space.
     */
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 32)->unique();
            $table->string('layout', 20)->default('masonry');
            $table->string('theme', 10)->default('light');
            $table->string('background_color', 7)->default('#ffffff');
            $table->boolean('has_animation')->default(true);
            $table->unsignedSmallInteger('display_limit')->default(12);
            $table->timestamps();

            $table->unique('space_id');

            $table->index(['space_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
