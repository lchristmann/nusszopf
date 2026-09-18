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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // `name` is the historical `users.name` column, publicly shown as the
            // account's "username" — it is set once at registration and, per the
            // confirmed historical permission model (docs/domain/permissions.md),
            // was never editable afterward by any role. Nusszopf 2 preserves that:
            // no update path for it exists anywhere in this slice.
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Local-disk avatar path (docs/rewrite/architecture-decisions.md,
            // "Object storage: required for v1 or deferred" — local disk in v1).
            // Historically populated only via social login; unset in this slice
            // since Socialite is deferred (docs/authentication/README.md §3).
            $table->string('picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
