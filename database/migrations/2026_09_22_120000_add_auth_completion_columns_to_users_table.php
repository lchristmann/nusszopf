<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 7 (docs/rewrite/master-roadmap.md, "Slice 7 — Authentication
 * completion"): Laravel-native equivalents of what Auth0 owned historically.
 *
 * - `email_verified_at`: decision A-3 (docs/rewrite/decisions-register.md) —
 *   a new, never-historical gate that does not block login/registration, only
 *   two specific product actions (publishing the personal contact, the future
 *   newsletter subscription).
 * - `google_id`: identifies an account created/linked via Google Socialite
 *   login without re-matching by e-mail on every subsequent login.
 * - `password` becomes nullable: an account created via Google has no local
 *   password at all (there is nothing to reset it to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('google_id')->nullable()->unique()->after('picture');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'google_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
