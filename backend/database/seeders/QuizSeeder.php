<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Group;
use App\Models\User;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure you have at least one admin/lecturer and one group
        $creator = User::where('email', 'testlecturer2@gmail.com')->first();
        $group = Group::first();

        if (!$creator || !$group) {
            $this->command->warn('Please ensure you have an Admin user and a Group created first.');
            return;
        }

        // 1. Create the Quiz
        $quiz = Quiz::create([
            'title' => 'Data Structures Foundation',
            'description' => 'A basic test on core data structures.',
            'status' => 'ANNOUNCED', // Must be ANNOUNCED so students can see it
            'creator_id' => $creator->id,
            'group_id' => $group->id,
            'time_limit' => 15, // 15 minutes
            'auto_submit' => true,
            'start_time' => now(),
        ]);

        // 2. Add Questions
        Question::create([
            'quiz_id' => $quiz->id,
            'text' => 'Which of the following is a linear data structure?',
            'options' => [
                'A' => 'Tree',
                'B' => 'Graph',
                'C' => 'Stack',
                'D' => 'Heap'
            ],
            'correct_answer' => 'C',
            'points' => 5,
        ]);

        Question::create([
            'quiz_id' => $quiz->id,
            'text' => 'What is the time complexity of searching in a perfectly balanced Binary Search Tree?',
            'options' => [
                'A' => 'O(1)',
                'B' => 'O(n)',
                'C' => 'O(log n)',
                'D' => 'O(n log n)'
            ],
            'correct_answer' => 'C',
            'points' => 5,
        ]);
    }
}