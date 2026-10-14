<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedInteger('points_per_post')->default(1)->after('user_id');
            $table->unsignedInteger('points_per_quiz_completion')->default(5)->after('points_per_post');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['points_per_post', 'points_per_quiz_completion']);
        });
    }
};