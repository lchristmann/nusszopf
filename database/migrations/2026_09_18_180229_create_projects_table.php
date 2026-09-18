<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema follows the historical `public.projects` table
     * (docs/domain/entities.md, `Project` entity) in full, even though this
     * first vertical slice's single-form creation screen only exercises
     * title/goal/description/visibility — the four-step wizard added in the
     * second slice populates the rest without a further schema change
     * (docs/rewrite/first-slice.md, "Final Nusszopf behavior vs. temporary
     * implementation-slice scaffolding").
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('goal');
            $table->text('description');
            // Structured rich-text-editor document backing `description`,
            // once the wizard's rich-text field replaces this slice's plain
            // textarea (docs/rewrite/architecture-decisions.md, "Rich-text
            // editor replacement for Slate"). Unused/null until then.
            $table->jsonb('description_template')->nullable();

            // { remote: bool, searchTerm: string, data: object } —
            // docs/domain/entities.md. Not collected by this slice's form.
            $table->jsonb('location')->nullable();
            // { flexible: bool, from: string, to: string } (dd.MM.yyyy).
            $table->jsonb('period')->nullable();

            $table->text('team')->nullable();
            $table->jsonb('team_template')->nullable();
            $table->string('motto')->nullable();

            // BUG-007 fix (docs/rewrite/bugs.md): historically unconstrained
            // text with no CHECK constraint. A real enum/CHECK is pure data-
            // integrity hardening — the value set (`private`/`public`) and
            // the `private` default are otherwise unchanged from history.
            $table->enum('visibility', ['private', 'public'])->default('private');

            $table->string('contact')->default('mail@nusszopf.org');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
