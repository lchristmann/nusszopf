<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The historical `public.leads` table (docs/domain/entities.md, `Lead`): a
     * newsletter subscriber identified by a unique e-mail, linked to an account
     * only by a matching address (no foreign key, as historically).
     *
     * `hasConfirmed` becomes `confirmed_at`, and the `privacy` boolean becomes a
     * consent record — `requested_at`, `source`, `consent_version` — decision
     * A-1 and BUG-011 (docs/rewrite/intentional-changes.md, "Double opt-in on
     * every newsletter path"). No IP address is stored.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name', 50);
            $table->string('source', 20);
            $table->string('consent_version', 50);
            $table->timestamp('requested_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['confirmed_at', 'requested_at']);
        });

        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source IN ('form', 'registration', 'profile'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
