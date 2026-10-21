<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // null = top-level block of the document
            $table->uuid('parent_id')->nullable();

            // fractional index among siblings; must sort byte-wise, hence the binary collation
            $position = $table->string('position', 128);
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'])) {
                $position->collation('utf8mb4_bin');
            }

            $table->string('type', 64);
            $table->json('props')->nullable();
            // pageBreakBefore, anchorId, draft, hidden, assignee, status
            $table->json('options')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_id', 'parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
