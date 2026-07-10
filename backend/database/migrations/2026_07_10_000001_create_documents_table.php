<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector for RAG embeddings; skipped on sqlite (test runs).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('title');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // nomic-embed-text produces 768-dimension vectors.
            DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(768)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
