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
    Schema::create('blacklists', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained(); // The student being watched/banned
        $table->integer('warning_count')->default(0);
        $table->string('status')->default('ACTIVE'); // e.g., 'ACTIVE', 'SUSPENDED'
        $table->dateTime('expiry_date')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklists');
    }
};
