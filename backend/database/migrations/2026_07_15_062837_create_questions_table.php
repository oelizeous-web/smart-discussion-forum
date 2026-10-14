<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            // Links the question to the specific quiz
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            
            $table->text('text'); // The actual question, e.g., "What is 2+2?"
            
            // JSON column to store options like {"A": "3", "B": "4", "C": "5"}
            $table->json('options'); 
            
            // Stores the correct key, e.g., "B"
            $table->string('correct_answer'); 
            
            // How many marks this specific question is worth
            $table->integer('points')->default(1); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};