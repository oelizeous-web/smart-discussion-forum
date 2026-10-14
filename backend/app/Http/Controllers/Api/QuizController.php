<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Http\Resources\MarkResource;
use App\Services\QuizService;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Mark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    protected QuizService $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasAnyRole(['lecturer', 'admin'])) {
            $quizzes = Quiz::where('creator_id', $user->id)
                ->with('group')
                ->latest()
                ->get();
        } else {
            $quizzes = Quiz::where('status', 'PUBLISHED')
                ->whereHas('group.members', fn ($q) => $q->where('users.id', $user->id))
                ->with('group')
                ->latest()
                ->get();
        }

        $marks = Mark::where('user_id', $user->id)->pluck('score', 'quiz_id');

        $data = $quizzes->map(function ($quiz) use ($marks) {
            $row = [
                'id'          => $quiz->id,
                'title'       => $quiz->title,
                'description' => $quiz->description,
                'status'      => $quiz->status,
                'group_id'    => $quiz->group_id,
                'group_name'  => $quiz->group->name ?? '',
                'time_limit'  => $quiz->time_limit,
                'start_time'  => $quiz->start_time,
                'attempted'   => $marks->has($quiz->id),
            ];

            if ($marks->has($quiz->id)) {
                $row['score'] = $marks->get($quiz->id);
            }

            return $row;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
    public function store(Request $request, $groupId)
    {
        // 1. Validate the combined payload (Quiz Settings + Questions Matrix)
        $validated = $request->validate([
            'title'                      => 'required|string|max:100',
            'description'                => 'required|string',
            'time_limit'                 => 'required|integer|min:1',
            'status'                     => 'required|in:DRAFT,PUBLISHED',
            'start_time'                 => 'nullable|date',
            'auto_submit'                => 'boolean',
            
            // Nested Questions Array Validation
            'questions'                  => 'required|array|min:1',
            'questions.*.text'           => 'required|string',
            'questions.*.points'         => 'required|integer|min:1',
            'questions.*.correct_answer' => 'required|in:A,B,C,D',
            'questions.*.options'        => 'required|array',
            'questions.*.options.A'      => 'required|string',
            'questions.*.options.B'      => 'required|string',
            'questions.*.options.C'      => 'required|string',
            'questions.*.options.D'      => 'required|string',
        ]);

        try {
            $creatorId = Auth::id() ?? 1; // Fallback for testing Java GUI without auth tokens yet

            // 2. Delegate to Service Layer (Handles DB Transaction)
            $quiz = $this->quizService->createQuiz(
                $validated, 
                $validated['questions'], 
                (int)$groupId, 
                $creatorId
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Assessment constructed successfully.',
                'data'    => new QuizResource($quiz) // Using your Resource wrapper
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to build assessment.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/quizzes/{id}
     * Fetches quiz details, provides the authoritative server time for Java GUI countdowns,
     * and starts (or resumes) the current user's QuizAttempt — mirroring the guard clauses
     * and firstOrCreate() logic in resources/views/livewire/student/quiz-attempt.blade.php
     * so web and desktop enforce identical rules.
     */
    public function show(Request $request, $id)
    {
        $quiz = Quiz::with('questions')->findOrFail($id);
        $user = $request->user();

        $attemptResult = $this->quizService->getOrStartAttempt($quiz, $user);

        if ($attemptResult['blocked']) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'quiz'        => new QuizResource($quiz),
                    'server_time' => now(),
                    'blocked'     => true,
                    'reason'      => $attemptResult['reason'],
                    'message'     => $attemptResult['message'],
                    'attempt_id'  => null,
                ],
            ]);
        }

        $attempt = $attemptResult['attempt'];
        $endsAt = $attempt->start_time->copy()->addMinutes($quiz->time_limit);
        if ($quiz->start_time) {
            $globalEndsAt = $quiz->start_time->copy()->addMinutes($quiz->time_limit);
            $endsAt = $endsAt->greaterThan($globalEndsAt) ? $globalEndsAt : $endsAt;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'quiz'                => new QuizResource($quiz),
                'server_time'         => now(),
                'ends_at'             => $endsAt,
                'blocked'             => false,
                'attempt_id'          => $attempt->id,
                'time_remaining_secs' => $attemptResult['time_remaining_secs'],
            ]
        ]);
    }
    /**
     * POST /api/attempts/{attemptId}/submit
     * Handles assessment submission, scoring, and server-side time limit enforcement.
     */
    public function submit(Request $request, $attemptId)
    {
        $validated = $request->validate([
            'answers'        => 'required|array',
            'auto_submitted' => 'boolean'
        ]);

        $attempt = QuizAttempt::with('quiz')->findOrFail($attemptId);
        $quiz = $attempt->quiz;
        
        // SERVER-SIDE PROTECTION: 
        // Calculate when the attempt should have strictly ended based on when the user started.
        $quizEndTime = $attempt->start_time->copy()->addMinutes($quiz->time_limit);
        
        // If the server receives the request AFTER the deadline, force the auto_submitted flag
        $isLate = now()->greaterThan($quizEndTime);
        $autoSubmitted = $isLate ? true : ($validated['auto_submitted'] ?? false);

        try {
            // Route the submission to the Service to handle scoring and Mark generation
            $score = $this->quizService->submitAttempt(
                (int)$attemptId, 
                $validated['answers'], 
                $autoSubmitted
            );

            return response()->json([
                'success' => true,
                'message' => $isLate ? 'Time expired. Assessment auto-submitted.' : 'Quiz submitted successfully.',
                'score'   => $score
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process submission.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/quizzes/{quizId}/report
     * Generates a performance report, ensuring the quiz has officially concluded.
     */
    public function report($quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        
        // The requirement: "all members... see the performance report"
        // Ensure the quiz has a start time and the strict time limit has passed globally
        if ($quiz->start_time && now()->isBefore($quiz->start_time->copy()->addMinutes($quiz->time_limit))) {
            return response()->json([
                'success' => false,
                'message' => 'Report not available until the assessment concludes globally.'
            ], 403);
        }

        // Assuming your 'Mark' model has a 'user' relationship, not 'student' (based on standard schema)
        $marks = Mark::where('quiz_id', $quizId)->with('user')->get();
        
        return response()->json([
            'success' => true,
            'data'    => MarkResource::collection($marks)
        ]);
    }
}