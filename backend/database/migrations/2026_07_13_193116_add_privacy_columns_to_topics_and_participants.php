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
    Schema::table('topics', function (Blueprint $table) {
        $table->boolean('is_private')->default(false)->after('title');
    });

    Schema::table('thread_participants', function (Blueprint $table) {
        // Status can be: 'approved' or 'pending'
        $table->string('status')->default('approved')->after('user_id'); 
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics_and_participants', function (Blueprint $table) {
            $table->dropColumn('is_private');
            $table->dropColumn('status');
        });
    }
};
