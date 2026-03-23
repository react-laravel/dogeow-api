<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->integer('buy_price')->nullable()->default(null)->after('description');
        });

        // 设置药品固定价格
        $potions = [
            138 => 10,  // 轻型生命药水
            139 => 20,  // 生命药水
            140 => 40,  // 重型生命药水
            141 => 80,  // 超重型生命药水
            142 => 10,  // 轻型法力药水
            143 => 20,  // 法力药水
            144 => 40,  // 重型法力药水
            145 => 80,  // 超重型法力药水
        ];

        foreach ($potions as $id => $price) {
            DB::table('game_item_definitions')->where('id', $id)->update(['buy_price' => $price]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->dropColumn('buy_price');
        });
    }
};
