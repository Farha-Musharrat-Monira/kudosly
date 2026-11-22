<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only: three nullable columns are added to the existing
     * testimonials table so the values submitted for a space's optional
     * collection fields have somewhere to live. No existing column is
     * altered or dropped, and no existing row is modified.
     *
     * The columns match the predefined optional field keys exactly, so a
     * submitted value always has a fixed, typed home rather than being
     * stored in an untyped blob. Company name and the social profile url
     * are plain text. The profile photo holds a path on the private disk
     * rather than a public URL, because a customer's photo is private
     * customer data and must never be served from a public location.
     */
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('company_name', 255)->nullable()->after('email');
            $table->string('social_url', 255)->nullable()->after('company_name');
            $table->string('profile_photo', 255)->nullable()->after('social_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'social_url', 'profile_photo']);
        });
    }
};
