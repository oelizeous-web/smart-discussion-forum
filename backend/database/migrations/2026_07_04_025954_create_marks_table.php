<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->integer('score');
            
            // To ensure a student only gets one final mark per quiz, 
            // it is best practice to add a unique constraint:
            $table->unique(['user_id', 'quiz_id']);
            
            $table->timestamps(); // created_at acts as the CompletionDate
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};