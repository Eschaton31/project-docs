<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the browser tab that made the change, so a tab can skip its own changes when it syncs
        Schema::table('revisions', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('user_id');
        });

        /*
         * Who is editing what. target is "block:<uuid>" or "settings". Rows expire instead of relying on
         * a clean release, since tabs get closed and connections drop.
         */
        Schema::create('block_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('target', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id');
            $table->timestamp('expires_at');

            $table->unique(['document_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_locks');
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};
