<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_repositories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider')->default('github');
            $table->string('owner');
            $table->string('repo');
            $table->string('full_name');
            $table->string('html_url', 500);
            $table->string('default_branch')->nullable();
            $table->string('language')->nullable();
            $table->string('ecosystem')->nullable();
            $table->string('package_name')->nullable();
            $table->string('manifest_path')->nullable();
            $table->string('latest_version')->nullable();
            $table->string('latest_source_type')->nullable();
            $table->string('latest_release_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('latest_release_published_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'provider', 'owner', 'repo'], 'watched_repositories_user_repo_unique');
            $table->index(['user_id', 'latest_release_published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_repositories');
    }
};
