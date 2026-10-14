<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizService;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public Quiz $quiz;
    public $questions;
    public int $currentQuestionIndex = 0;
    public array $answers = []; 
    public array $markedForReview = [];
    public int $timeRemaining = 0;
    public QuizAttempt $attempt;

    public function mount($id) 
    {
        // 1. Load the Quiz and Questions
        $this->quiz = Quiz::with('questions')->findOrFail($id);
        $this->questions = $this->quiz->questions;

        $existingAttempt = QuizAttempt::where('quiz_id', $this->quiz->id)
        ->where('user_id', Auth::id())
        ->whereNotNull('submitted_at')
        ->first();

        if ($existingAttempt) {
            session()->flash('error', 'You have already submitted this quiz.');
            return $this->redirect(route('quizzes'), navigate: true);
        }
        if ($this->quiz->start_time && now()->isBefore($this->quiz->start_time)) {
                    session()->flash('error', 'This quiz has not started yet. Please wait until ' . \Carbon\Carbon::parse($this->quiz->start_time)->format('M d, Y h:i A'));
                    return redirect()->route('dashboard'); // Redirect them back to the quiz list
                }

        if ($this->quiz->hasClosed()) {
            session()->flash('error', 'This quiz has closed and is no longer accepting attempts.');
            return redirect()->route('quizzes');
        }

        // 2. Start or Resume the Attempt
        $this->attempt = QuizAttempt::firstOrCreate(
            ['quiz_id' => $this->quiz->id, 'user_id' => Auth::id(), 'submitted_at' => null],
            ['start_time' => now()]
        );

        // 3. Calculate time remaining, capped by the quiz's global closing time so a
        // late joiner only gets what's left of the window, not a fresh full timer.
        $individualDeadline = $this->attempt->start_time->copy()->addMinutes($this->quiz->time_limit);
        $deadline = $individualDeadline;

        if ($this->quiz->start_time) {
            $globalDeadline = \Carbon\Carbon::parse($this->quiz->start_time)->addMinutes($this->quiz->time_limit);
            $deadline = $individualDeadline->greaterThan($globalDeadline) ? $globalDeadline : $individualDeadline;
        }

        $this->timeRemaining = $deadline->greaterThan(now()) ? now()->diffInSeconds($deadline) : 0;
        
        // Pre-fill tracking arrays
        foreach ($this->questions as $q) {
            $this->answers[$q->id] = null;
            $this->markedForReview[$q->id] = false;
        }
    }

    public function setQuestion($index)
    {
        if ($index >= 0 && $index < count($this->questions)) {
            $this->currentQuestionIndex = $index;
        }
    }

    public function toggleReview()
    {
        $currentId = $this->questions[$this->currentQuestionIndex]->id;
        $this->markedForReview[$currentId] = !$this->markedForReview[$currentId];
    }

    // NOTE: logViolation() method has been completely removed to prevent the 500 DB Error.

public function submitAttempt(QuizService $quizService, $autoSubmitted = false)
{
    $quizService->submitAttempt($this->attempt->id, $this->answers, $autoSubmitted);
    
    session()->flash('success', "Quiz submitted successfully!");

    // Use this specific redirect syntax for Volt components:
    return $this->redirect(route('quizzes'), navigate: true);
}
}; ?>

<!-- Full-screen layout to enforce the lockdown requirement -->
<div x-data="{
    trackViolation() {
        // Open the global modal to WARN the user, without hitting the database
        $dispatch('open-confirm', { 
            title: 'Academic Warning', 
            message: 'Navigating away from the quiz window is strictly prohibited. Please stay on this page to complete your attempt.', 
            callback: () => { 
                // Do nothing, just let them close the warning and return to the quiz
            } 
        });
    }
}" @blur.window="trackViolation()">
<div>
    <div class="min-h-screen h-screen w-full bg-stone-50 flex flex-col font-sans text-slate-900 overflow-hidden" 
         x-data="quizAttempt({{ $timeRemaining }})">
        
        <!-- TOP NAVIGATION BAR -->
        <header class="h-16 bg-green-800 flex items-center justify-between gap-3 px-4 sm:px-6 shadow-md z-20 shrink-0">
            <div class="flex items-center gap-3">
                <!-- Mobile Question Nav Toggle (three-line hamburger) -->
                <button @click="qnavOpen = !qnavOpen" type="button" aria-label="Toggle question navigator" class="lg:hidden p-2 -ml-2 rounded-md text-white/90 hover:text-white hover:bg-green-900/50 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Exit Quiz Button -->
                <button @click="submitQuiz()" type="button" class="flex items-center gap-2 text-white/90 hover:text-white transition group">
                    <svg class="w-5 h-5 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="font-semibold text-sm hidden sm:inline">Exit</span>
                </button>
            </div>

            <!-- Quiz Title -->
            <h1 class="text-lg font-bold tracking-wide hidden md:block truncate text-white">{{ $quiz->title }}</h1>

            <!-- Timer -->
            <div class="flex items-center gap-2 bg-green-900/40 px-3 sm:px-4 py-1.5 rounded-full border border-green-600/50 shadow-inner shrink-0">
                <svg class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium text-green-100 hidden sm:block">Time Remaining</span>
                <span class="font-mono font-bold text-white tracking-wider" x-text="formatTime(timeRemaining)">--:--:--</span>
            </div>
        </header>

        <!-- MAIN WORKSPACE -->
        <div class="flex-1 flex overflow-hidden relative">

            <!-- Mobile backdrop for question navigator -->
            <div x-show="qnavOpen" x-cloak @click="qnavOpen = false"
                 x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/40 z-20 lg:hidden"></div>

            <!-- LEFT SIDEBAR: Question Navigation -->
            <aside
                :class="qnavOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 top-16 lg:top-0 w-72 bg-white border-r border-stone-200 flex flex-col shrink-0 z-30 shadow-xl transform transition-transform duration-200 ease-out lg:static lg:translate-x-0 lg:shadow-none">
                <div class="p-5 flex-1 overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-stone-300">
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-5">Questions</h2>
                    
                    <!-- Number Grid -->
                    <div class="grid grid-cols-4 gap-2.5 mb-8">
                        @foreach($questions as $index => $question)
                            <button 
                                wire:click="setQuestion({{ $index }})"
                                @click="qnavOpen = false"
                                class="h-10 rounded-md transition font-bold shadow-sm 
                                {{ $currentQuestionIndex === $index ? 'ring-2 ring-green-700 ring-offset-2 ring-offset-white' : '' }}
                                {{ $markedForReview[$question->id] ? 'bg-amber-500 text-white' : ($answers[$question->id] !== null ? 'bg-green-700 text-white' : 'bg-stone-100 text-slate-600 hover:bg-stone-200') }}">
                                {{ $index + 1 }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Legend -->
                    <div class="space-y-3 bg-stone-50 p-4 rounded-xl border border-stone-200">
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded shadow-sm bg-green-700"></span>
                            <span class="text-xs text-slate-600 font-semibold uppercase tracking-wide">Answered</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded shadow-sm bg-amber-500"></span>
                            <span class="text-xs text-slate-600 font-semibold uppercase tracking-wide">Review</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded shadow-sm bg-stone-300"></span>
                            <span class="text-xs text-slate-600 font-semibold uppercase tracking-wide">Not Answered</span>
                        </div>
                    </div>
                </div>

                <!-- Mark for Review Action -->
                <div class="p-5 border-t border-stone-200 bg-white">
                    <button wire:click="toggleReview" class="w-full py-2.5 rounded-lg border-2 border-stone-200 text-slate-600 hover:bg-stone-100 hover:text-slate-900 transition font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                        Toggle Review
                    </button>
                </div>
            </aside>

            <!-- CENTER: Question Area -->
            <main class="flex-1 flex flex-col bg-stone-50 overflow-y-auto">
                @php
                    // Safely grab the question, re-indexing keys to ensure 0, 1, 2... format
                    $currentQuestion = collect($questions)->values()->get($currentQuestionIndex);
                @endphp

                @if($currentQuestion)
                    <div class="max-w-4xl mx-auto w-full flex-1 flex flex-col p-5 sm:p-8 lg:p-12">
                        
                        <!-- Question Header -->
                        <div class="mb-10">
                            <h3 class="text-green-700 font-bold text-sm tracking-widest uppercase mb-4">
                                Question {{ $currentQuestionIndex + 1 }} of {{ count($questions) }}
                            </h3>
                            <p class="text-2xl text-slate-900 font-medium leading-relaxed">
                                {{ $currentQuestion->text ?? '' }}
                            </p>
                        </div>

                        <!-- Multiple Choice Options -->
                        <div class="space-y-4 flex-1">
                            @php
                                $options = is_string($currentQuestion->options) 
                                    ? json_decode($currentQuestion->options, true) 
                                    : $currentQuestion->options;
                            @endphp

                            @if($options)
                                @foreach($options as $key => $optionText)
                                    <label class="flex items-center gap-4 p-5 rounded-xl border transition cursor-pointer group shadow-sm
                                        {{ ($answers[$currentQuestion->id] ?? null) === $key ? 'border-green-600 bg-green-50' : 'border-stone-200 bg-white hover:bg-stone-50 hover:border-green-400' }}">
                                        
                                        <input type="radio" 
                                               wire:model.live="answers.{{ $currentQuestion->id }}" 
                                               value="{{ $key }}" 
                                               class="w-5 h-5 text-green-700 bg-white border-stone-300 focus:ring-green-600 focus:ring-offset-white">
                                        
                                        <span class="text-lg {{ ($answers[$currentQuestion->id] ?? null) === $key ? 'text-slate-900 font-medium' : 'text-slate-600 group-hover:text-slate-900' }}">
                                            {{ $key }}. {{ $optionText }}
                                        </span>
                                    </label>
                                @endforeach
                            @endif
                        </div>

                        <!-- Bottom Navigation -->
                        <div class="mt-12 pt-6 border-t border-stone-200 flex items-center justify-between">
                            <button 
                                wire:click="setQuestion({{ $currentQuestionIndex - 1 }})"
                                @if($currentQuestionIndex === 0) disabled @endif
                                class="px-6 py-3 rounded-lg border border-stone-200 bg-white text-slate-600 hover:bg-stone-100 hover:text-slate-900 transition font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                Previous
                            </button>
                            
                            @if($currentQuestionIndex < count($questions) - 1)
                                <button 
                                    wire:click="setQuestion({{ $currentQuestionIndex + 1 }})"
                                    class="px-8 py-3 rounded-lg bg-green-700 text-white font-bold hover:bg-green-600 transition shadow-lg shadow-green-900/10 flex items-center gap-2">
                                    Next
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            @endif
                        </div>
                        
                        <!-- Submit Action -->
                        <div class="mt-6 flex justify-end">
                            <button @click="submitQuiz()" 
                                    type="button" 
                                    class="w-full md:w-auto px-10 py-3.5 rounded-lg bg-green-800 text-white font-extrabold tracking-wide hover:bg-green-700 transition shadow-xl flex items-center justify-center gap-2 border border-green-600">
                                <svg class="w-5 h-5 transform -rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                Submit Quiz
                            </button>
                        </div>

                    </div>
                @else
                    <!-- Friendly Empty State if Quiz has no questions -->
                    <div class="flex-1 flex flex-col items-center justify-center p-8">
                        <svg class="w-16 h-16 text-stone-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h2 class="text-2xl font-bold text-slate-900 mb-2">No Questions Found</h2>
                        <p class="text-slate-500 text-center max-w-md">Your instructor hasn't added any questions to this quiz yet.</p>
                        <button @click="submitQuiz()" class="mt-6 px-6 py-2 bg-stone-200 hover:bg-stone-300 text-slate-800 rounded-lg transition">Exit Quiz</button>
                    </div>
                @endif
            </main>

        </div>
    </div>
</div>
<div x-data="{ showModal: false }" @open-submit-modal.window="showModal = true" x-show="showModal" 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    
    <div class="bg-white p-8 rounded-xl border border-stone-200 w-full max-w-sm shadow-2xl">
        <h3 class="text-xl font-bold text-slate-900 mb-2">Submit Quiz?</h3>
        <p class="text-slate-500 mb-6">Are you sure? You cannot change your answers after submission.</p>
        
        <div class="flex gap-3">
            <button @click="showModal = false" class="flex-1 px-4 py-2 bg-stone-100 text-slate-700 rounded-lg hover:bg-stone-200">Cancel</button>
            <button @click="@this.call('submitAttempt', false); showModal = false" 
                    class="flex-1 px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-600">
                Yes, Submit
            </button>
        </div>
    </div>
</div>
    </div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('quizAttempt', (initialSeconds) => ({
            timeRemaining: initialSeconds,
            timerInterval: null,
            qnavOpen: false,

            init() {
                if (this.timeRemaining > 0) {
                    this.timerInterval = setInterval(() => {
                        if (this.timeRemaining > 0) {
                            this.timeRemaining--;
                        } else {
                            clearInterval(this.timerInterval);
                            this.forceSubmit();
                        }
                    }, 1000);
                } else {
                    this.forceSubmit();
                }
            },

            formatTime(seconds) {
                if (seconds <= 0) return '00:00';
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = seconds % 60;
                return [h > 0 ? String(h).padStart(2, '0') : null, String(m).padStart(2, '0'), String(s).padStart(2, '0')]
                    .filter(Boolean).join(':');
            },

 // Inside your Alpine.data('quizAttempt', ...) object

submitQuiz() {
    // Instead of browser confirm(), we dispatch the event to show our custom modal
    this.$dispatch('open-submit-modal');
},

forceSubmit() {
    clearInterval(this.timerInterval);
    // Use @this to call the Livewire method
    @this.call('submitAttempt', true);
}
        }));
    });
</script>