<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI prose reviews (admin only): a queued review of a document or one topic, and the suggestions it produced.
     */
    public function up(): void
    {
        Schema::create('prose_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // null = the whole document
            $table->uuid('scope_block_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // key into config('ai-review.models'): sonnet, opus
            $table->string('model', 16);
            // text, restructure
            $table->json('kinds');
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            // the document's seq when the review read it
            $table->unsignedInteger('document_seq')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
        });

        Schema::create('prose_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('prose_reviews')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('severity', 8);
            $table->string('rule');
            $table->text('rationale');
            // text: the one block; restructure: the neighbouring siblings it replaces, in order
            $table->json('block_ids');
            // block id => version when suggested
            $table->json('block_versions');

            // text suggestions: where in the block's props, and the change
            $table->string('field')->nullable();
            $table->string('path')->nullable();
            $table->text('original')->nullable();
            $table->text('suggested')->nullable();

            // restructure suggestions: the new nodes, placed under parent_id (null = document root)
            $table->json('replacement')->nullable();
            $table->uuid('parent_id')->nullable();

            $table->string('status', 16)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prose_suggestions');
        Schema::dropIfExists('prose_reviews');
    }
};
