<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scaffolds: a review that drafts documentation from uploaded R scripts and the admin's notes on how they are
     * run, with the document as it is for context. New sections are placed in the template's order and cite the
     * script lines they come from.
     */
    public function up(): void
    {
        Schema::table('prose_reviews', function (Blueprint $table) {
            // review (the document's prose) or scaffold (documentation from source files)
            $table->string('mode', 16)->default('review')->after('scope_block_id');
            // scaffolds: how the scripts are run, in the admin's words
            $table->text('run_notes')->nullable()->after('brief');
        });

        Schema::create('prose_review_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('prose_reviews')->cascadeOnDelete();
            // F1, F2… in upload order; what the model cites
            $table->string('ref', 8);
            // the path as uploaded, normalized (R/clean.R)
            $table->string('name');
            // the script the admin opens first
            $table->boolean('entry')->default(false);
            $table->string('disk', 32);
            $table->string('path');
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->unsignedInteger('chars');
            $table->unsignedInteger('lines');
            // [{line, kind}] for every secret blanked out before the file was stored
            $table->json('redactions');
            $table->timestamps();

            $table->unique(['review_id', 'ref']);
        });

        Schema::table('prose_suggestions', function (Blueprint $table) {
            // [{file: F2, from: 10, to: 42}]: the script lines a scaffold suggestion is drawn from
            $table->json('citations')->nullable()->after('sources');
            // scaffold sections: their place in the template, so they're inserted in that order
            $table->unsignedSmallInteger('order')->nullable()->after('citations');
            // scaffold sections: the root block accepting created, for placing later sections next to it
            $table->uuid('inserted_id')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('prose_suggestions', function (Blueprint $table) {
            $table->dropColumn(['citations', 'order', 'inserted_id']);
        });
        Schema::dropIfExists('prose_review_sources');
        Schema::table('prose_reviews', function (Blueprint $table) {
            $table->dropColumn(['mode', 'run_notes']);
        });
    }
};
