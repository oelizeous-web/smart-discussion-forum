<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome'); 
});

// --- Public/Unprotected Routes ---
Route::get('/account-blacklisted', function () {
    return view('account-blacklisted'); 
})->name('account.blacklisted');

Route::post('/logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// --- Protected Dashboard & Profile ---
Route::middleware(['auth', 'verified', 'check.blacklist'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
});

// --- Administrator Routes ---
// Grouped to cleanly enforce interface access privilege control[cite: 2]
Route::middleware(['auth', 'role:admin'])->group(function () {
    Volt::route('/members', 'admin.members')->name('admin.members');
    
    // The new Settings Route
    Volt::route('/settings', 'admin.settings')->name('admin.settings');
});

// --- Student Routes (Protected by Blacklist) ---
Route::middleware(['auth', 'verified', 'role:student', 'check.blacklist'])->group(function () {
    
    Volt::route('forums', 'student.chat-interface')->name('forums');
    
    Volt::route('quizzes', 'student.quiz-dashboard')->name('quizzes');
    Volt::route('quizzes/{id}/attempt', 'student.quiz-attempt')->name('quiz.attempt');

});

// --- Lecturer Routes ---
Route::middleware(['auth', 'role:lecturer'])->group(function () {
    Volt::route('/lecturer/students', 'lecturer.students')->name('lecturer.students');
    Volt::route('/lecturer/grades', 'lecturer.grades')->name('lecturer.grades');
    Volt::route('/lecturer/quizzes', 'lecturer.quizzes')->name('lecturer.quizzes');
    Volt::route('/lecturer/reports', 'lecturer.reports')->name('reports');
});

require __DIR__.'/auth.php';