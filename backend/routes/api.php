<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\QuizController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiDocsController;
use App\Http\Controllers\Api\QuizAttemptController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;

Route::get('/', [ApiDocsController::class, 'index']); 

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Java GUI & General API)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // --- Administrator Only ---
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/register-lecturer', [AuthController::class, 'registerLecturer']);
        Route::get('/groups', [GroupController::class, 'adminIndex']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/lecturers', [AdminController::class, 'lecturers']);
        Route::post('/users/{id}/warn', [AdminController::class, 'warnUser']);
        Route::post('/users/{id}/suspend', [AdminController::class, 'suspendUser']);
        Route::post('/users/{id}/reinstate', [AdminController::class, 'reinstateUser']);
        Route::get('/flags', [AdminController::class, 'flags']);
        Route::post('/flags/{id}/resolve', [AdminController::class, 'resolveFlag']);
    });

    // --- Quiz Management (Lecturer/Admin) ---
    Route::middleware(['role:lecturer|admin'])->group(function () {
        // Generates POST /api/groups/{group}/quizzes mapped to QuizController@store
        Route::apiResource('groups.quizzes', QuizController::class)->only(['store']);
        
        // Generates GET /api/quizzes/{quiz}/report mapped to QuizController@report
        Route::get('/quizzes/{quiz}/report', [QuizController::class, 'report']);

        // --- Lecturer/Admin: Group Roster & Gradebook ---
        Route::get('/groups/{group}/students', [GroupController::class, 'students']);
        Route::get('/groups/{group}/grades', [GroupController::class, 'grades']);
        Route::get('/groups/manageable', [GroupController::class, 'manageableIndex']);
        Route::get('/groups/{group}/participation-criteria', [GroupController::class, 'participationCriteria']);
        Route::post('/groups/{group}/participation-criteria', [GroupController::class, 'updateParticipationCriteria']);
    });
    
    // --- General Group Actions ---
    Route::post('/groups/join', [GroupController::class, 'join']); 
    Route::apiResource('groups', GroupController::class)->only(['index', 'store']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Forum & Interaction Routes (Protected by Blacklist)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'check.blacklist'])->group(function () {
    
    // --- Topics ---
    Route::apiResource('groups.topics', TopicController::class)->only(['index', 'store']);
    Route::post('/topics/request-access', [TopicController::class, 'requestAccess']);
    Route::post('/topics/approve', [TopicController::class, 'approve']);
    Route::post('/topics/warn', [TopicController::class, 'warn']);

    // --- Quizzes (Student Access) ---
    Route::get('/quizzes', [QuizController::class, 'index']); // Assuming you have an index method
    
    // Generates GET /api/quizzes/{quiz} mapped to QuizController@show
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show']); 
    
    // Generates POST /api/attempts/{attempt}/submit mapped to QuizController@submit
    Route::post('/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit']);

    // --- Posts & Moderation ---
    Route::apiResource('topics.posts', PostController::class)->only(['index', 'store']);
    Route::post('/posts/{post}/flag', [PostController::class, 'flag']);
});