<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stripe delivers the same event more than once when a webhook is retried,
     * so every processed event is recorded here and a repeat delivery is
     * recognised and skipped. The event identifier is the primary key, which
     * makes the insert itself the guard: a second delivery of the same event
     * collides and is refused by the database.
     *
     * The table is created new. No existing table is altered or dropped and no
     * existing row is modified. The column types are plain so the same schema
     * works on MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->string('stripe_event_id')->primary();
            $table->string('type');
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
