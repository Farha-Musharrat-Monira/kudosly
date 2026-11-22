<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each row records one of the predefined collection fields for a space,
     * together with whether it is enabled and whether it is required.
     */
    public function up(): void
    {
        Schema::create('space_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 80);
            $table->boolean('enabled')->default(false);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['space_id', 'key']);
            $table->index(['space_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_fields');
    }
};
