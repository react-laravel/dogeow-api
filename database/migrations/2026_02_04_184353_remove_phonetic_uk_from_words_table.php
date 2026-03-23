<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('phonetic_uk');
        });
    }

    public function down(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->string('phonetic_uk')->nullable()->after('content');
        });
    }
};
