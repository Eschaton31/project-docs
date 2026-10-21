<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side PDF exports: queued, rendered by headless Chrome, then downloaded.
     */
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // set when exporting a published version instead of the live document
            $table->foreignId('snapshot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('mode', 8)->default('draft');
            $table->string('status', 16)->default('queued');
            $table->string('file_name');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
