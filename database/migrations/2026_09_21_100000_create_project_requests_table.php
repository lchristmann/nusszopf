<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The historical `public.requests` table (docs/domain/entities.md,
     * `Request`), named `project_requests` after the decided model name
     * (register B7). A request belongs to exactly one project and is deleted
     * with it (`1606046495095_refactor_cascade_deletes`).
     *
     * `category` was historically free text with no constraint at all; as with
     * `projects.visibility` (BUG-007) the five real values become a CHECK
     * constraint — pure data-integrity hardening, the value set is unchanged.
     * `title` is capped at the historical form schema's 40 characters.
     */
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();

            $table->string('title', 40);
            $table->enum('category', ['companions', 'rooms', 'materials', 'financials', 'others']);
            // The plain-text projection (search) and the rich-text document it
            // is derived from, like `projects.description(_template)`.
            $table->text('description');
            $table->jsonb('description_template');

            $table->timestamps();

            // Every read lists a project's requests newest first.
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requests');
    }
};
