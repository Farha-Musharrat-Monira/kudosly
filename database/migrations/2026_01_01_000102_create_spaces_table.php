<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The slug is globally unique because a space's public URL is /s/{slug},
     * so the slug alone identifies a space. The theme is a plain string
     * rather than an enum so the schema stays portable between MySQL and
     * SQLite; valid values live in App\Models\Space::THEMES.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->text('testimonial_request')->nullable();
            $table->string('theme', 32)->default('minimal_light');
            $table->boolean('collect_rating')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
