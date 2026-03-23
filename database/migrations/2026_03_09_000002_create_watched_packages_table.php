<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('source_provider', 32)->default('github');
            $table->string('source_owner', 128);
            $table->string('source_repo', 128);
            $table->string('source_url', 500);
            $table->string('ecosystem');
            $table->string('package_name', 128);
            $table->string('manifest_path')->nullable();
            $table->string('current_version_constraint')->nullable();
            $table->string('normalized_current_version')->nullable();
            $table->string('latest_version')->nullable();
            $table->string('watch_level')->default('minor');
            $table->string('latest_update_type')->nullable();
            $table->string('registry_url', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(
                ['user_id', 'source_provider', 'source_owner', 'source_repo', 'ecosystem', 'package_name'],
                'watched_packages_unique'
            );
            $table->index(['user_id', 'latest_update_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_packages');
    }
};
