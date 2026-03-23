<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('watched_repository_id');
            $table->string('source_type');
            $table->string('source_id')->nullable();
            $table->string('version')->nullable();
            $table->string('title')->nullable();
            $table->string('release_url', 500)->nullable();
            $table->longText('body')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('watched_repository_id');
            $table->unique(
                ['watched_repository_id', 'source_type', 'source_id'],
                'repository_updates_repo_source_unique'
            );
            $table->index(['watched_repository_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_updates');
    }
};
