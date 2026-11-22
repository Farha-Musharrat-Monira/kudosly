<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The table is created empty in this phase so the dashboard can report
     * accurate database-driven counts. Submission and moderation arrive with
     * the collection phase. Customer emails are stored for the account
     * owner only and must never be exposed on a public surface.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('consent_to_share')->default(false);
            $table->string('status', 20)->default('pending');
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_heart')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['space_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
