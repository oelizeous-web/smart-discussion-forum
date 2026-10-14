<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Services\QuizService;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    protected QuizService $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    public function submit(Request $request, $attemptId)
    {
        // 1. Validate incoming payload
        $validated = $request->validate([
            'answers'        => 'required|array',
            'auto_submitted' => 'boolean'
        ]);

        // 2. Fetch the attempt and its parent quiz
        $attempt = QuizAttempt::with('quiz')->findOrFail($attemptId);
        $quiz = $attempt->quiz;
        
            $quizEndTime = $attempt->start_time->copy()->addMinutes($quiz->time_limit);
            if ($quiz->start_time) {
                $globalEndTime = $quiz->start_time->copy()->addMinutes($quiz->time_limit);
                $quizEndTime = $quizEndTime->greaterThan($globalEndTime) ? $globalEndTime : $quizEndTime;
            }
            $isLate = now()->greaterThan($quizEndTime->copy()->addSeconds(30));
            $autoSubmitted = $isLate ? true : ($validated['auto_submitted'] ?? false);

        try {
            // 4. Pass to the unified Service Layer
            $score = $this->quizService->submitAttempt(
                (int)$attemptId, 
                $validated['answers'], 
                $autoSubmitted
            );

            return response()->json([
                'success' => true,
                'message' => $isLate ? 'Time expired. Assessment auto-submitted.' : 'Quiz submitted successfully.',
                'data'    => ['score' => $score]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process submission.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}