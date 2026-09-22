<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The historical filename itself encoded the version (`{id}|nz_v{n}.jpeg`,
     * `pages/api/upload.js`'s `createFilename`), parsed back out of the
     * previous `picture` value on every upload. A dedicated counter is the
     * same versioned-filename scheme (`docs/rewrite/master-roadmap.md`,
     * "Slice 8") without regex-parsing a stored string to recover it —
     * more robust, same product behavior (old versions are still replaced,
     * not accumulated).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('avatar_version')->default(0)->after('picture');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_version');
        });
    }
};
