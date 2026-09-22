<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The historical `public.projects_analytics` table (docs/domain/entities.md,
     * `ProjectAnalytics`) — a 1:1 extension of `projects`, keyed by `project_id`
     * itself rather than its own `id`. Historically also carried
     * `contactRequests`, which is not reproduced (no confirmed frontend call
     * site ever writes it — BUG-017, `docs/rewrite/bugs.md`,
     * `docs/rewrite/intentional-changes.md`).
     *
     * BUG-001's fix (docs/rewrite/bugs.md): the historical `views`/
     * `contactRequests` columns were insertable/updatable by any caller for
     * any project via Hasura's open permissions. Nusszopf 2 never exposes
     * `views` as a client-writable field through any authorization boundary —
     * only server-side controller logic (`ProjectDetail::mount()`) increments
     * it. The historical `<= 1,000,000` CHECK is kept as a sanity bound, not
     * as the enforcement mechanism (that's the missing write path itself).
     */
    public function up(): void
    {
        Schema::create('project_analytics', function (Blueprint $table) {
            $table->foreignUuid('project_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
        });

        DB::statement('ALTER TABLE project_analytics ADD CONSTRAINT project_analytics_views_check CHECK (views <= 1000000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_analytics');
    }
};
