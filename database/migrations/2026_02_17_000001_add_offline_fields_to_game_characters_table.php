<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->timestamp('last_online')->nullable()->after('last_combat_at')->comment('最后在线时间');
            $table->timestamp('claimed_offline_at')->nullable()->after('last_online')->comment('最后领取离线奖励时间');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn(['last_online', 'claimed_offline_at']);
        });
    }
};
