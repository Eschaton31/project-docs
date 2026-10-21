<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per change. Doubles as the event log clients replay to catch up (seq > since).
     */
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seq');
            // null for document-level changes (settings)
            $table->uuid('block_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('op', 32);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
