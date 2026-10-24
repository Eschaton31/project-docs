<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviews run in Claude Code are exported and imported separately; the text they were given (and how it was
     * split) is kept so the results are read against exactly that, whatever changed in the document meanwhile.
     */
    public function up(): void
    {
        Schema::table('prose_reviews', function (Blueprint $table) {
            // {nodes: block tree as exported, chunks: block refs per chunk}; cleared once imported
            $table->json('snapshot')->nullable()->after('document_seq');
        });
    }

    public function down(): void
    {
        Schema::table('prose_reviews', function (Blueprint $table) {
            $table->dropColumn('snapshot');
        });
    }
};
