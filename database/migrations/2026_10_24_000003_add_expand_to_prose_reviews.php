<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand suggestions add new blocks next to (or inside) an existing one. The admin can give the reviewer a
     * brief to draw on, and each expansion records where its content came from so invented detail can be checked.
     */
    public function up(): void
    {
        Schema::table('prose_reviews', function (Blueprint $table) {
            // what the admin wants covered, in their words; part of every chunk's prompt
            $table->text('brief')->nullable()->after('kinds');
        });

        Schema::table('prose_suggestions', function (Blueprint $table) {
            // expand suggestions: before|after the block in block_ids, or at the start|end of its children
            $table->string('position', 8)->nullable()->after('parent_id');
            // expand suggestions: document, brief, general (general knowledge needs a fact check)
            $table->json('sources')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('prose_suggestions', function (Blueprint $table) {
            $table->dropColumn(['position', 'sources']);
        });
        Schema::table('prose_reviews', function (Blueprint $table) {
            $table->dropColumn('brief');
        });
    }
};
