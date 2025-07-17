<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->text('content'); // The knowledge/insight content
            $table->string('repo')->nullable(); // Git repository (e.g., 'conduit-ui/conduit')
            $table->string('branch')->nullable(); // Git branch name
            $table->string('commit_sha')->nullable(); // Git commit SHA
            $table->string('author')->nullable(); // Git author
            $table->string('project_type')->nullable(); // Detected project type (laravel-zero, etc.)
            $table->string('file_path')->nullable(); // Current file context (future)
            $table->json('tags')->nullable(); // Searchable tags
            $table->timestamps();

            // Indexes for search performance
            $table->index(['repo', 'branch']);
            $table->index('created_at');
            $table->index('author');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
