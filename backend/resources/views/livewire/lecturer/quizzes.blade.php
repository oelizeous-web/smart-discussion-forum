<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Group;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    // Quiz Setup State
    public string $title = '';
    public string $description = '';
    public string $status = 'DRAFT';
    public ?int $group_id = null;
    public ?int $time_limit = 30; // Default 30 mins
    public bool $auto_submit = true;
    public ?string $start_time = null;
    public bool $showQuizLibrary = false;
    public ?int $expandedQuizId = null;

    // Available Groups for the Dropdown
    public $availableGroups = [];

    // Questions State mapping to `questions` table schema
    public array $questions = [];

    public function mount()
    {
        // Load real groups from the database
        $this->availableGroups = Group::orderBy('name')->get();
        if ($this->availableGroups->isNotEmpty()) {
            $this->group_id = $this->availableGroups->first()->id;
        }

        // Initialize with one empty question
        $this->addQuestion();
    }

    public function addQuestion(): void
    {
        $this->questions[] = [
            'text' => '',
            'options' => [
                'A' => '',
                'B' => '',
                'C' => '',
                'D' => ''
            ],
            'correct_answer' => 'A', // Default to A
            'points' => 1
        ];
    }

        public function toggleQuizLibrary(): void
        {
            $this->showQuizLibrary = ! $this->showQuizLibrary;
        }

        public function toggleQuizDetails(int $quizId): void
        {
            $this->expandedQuizId = $this->expandedQuizId === $quizId ? null : $quizId;
        }

        public function getMyQuizzesProperty()
        {
            return Quiz::where('creator_id', Auth::id() ?? 1)
                ->with('questions', 'group')
                ->withCount('attempts')
                ->latest()
                ->get();
        }

    public function removeQuestion(int $index): void
    {
        if (count($this->questions) > 1) {
            unset($this->questions[$index]);
            $this->questions = array_values($this->questions);
        }
    }

    public function publishQuiz(QuizService $quizService): void
    {
        // 1. Validate Input
        $validated = $this->validate([
            'title' => 'required|string|max:100',
            'description' => 'required|string',
            'group_id' => 'required|exists:groups,id',
            'time_limit' => 'required|integer|min:1',
            'status' => 'required|in:DRAFT,PUBLISHED',
            'start_time' => 'nullable|date',
            
            // Validate nested questions
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.correct_answer' => 'required|in:A,B,C,D',
            'questions.*.options.A' => 'required|string',
            'questions.*.options.B' => 'required|string',
            'questions.*.options.C' => 'required|string',
            'questions.*.options.D' => 'required|string',
        ], [
            'questions.*.text.required' => 'All questions must have text.',
            'questions.*.options.*.required' => 'All options (A, B, C, D) must be filled out.',
        ]);

        // 2. Delegate to the unified QuizService
        $quizService->createQuiz(
            $validated, 
            $this->questions, 
            $this->group_id, 
            Auth::id() ?? 1
        );

        // 3. Reset UI state & notify user
        $this->reset(['title', 'description', 'time_limit', 'start_time', 'questions']);
        $this->status = 'DRAFT';
        $this->addQuestion();

        session()->flash('success', 'Assessment configuration published to database successfully.');
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 bg-zinc-50 min-h-screen text-zinc-900 font-sans">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-zinc-200 p-6 rounded-2xl shadow-sm">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-zinc-900">Assessment Builder</h1>
            <p class="text-sm font-medium text-zinc-500 mt-1">Configure quiz parameters and construct standardized question matrices.</p>
        </div>
        <div class="flex gap-3">
            <button wire:click="toggleQuizLibrary" type="button" class="bg-white border border-zinc-200 text-[#24a065] font-bold text-xs px-4 py-2 rounded-lg hover:bg-emerald-50 hover:border-emerald-200 transition-colors">
                {{ $showQuizLibrary ? 'Hide My Quizzes' : 'My Quizzes' }} ({{ $this->myQuizzes->count() }})
            </button>
        </div>
    </div>

    <!-- Notifications -->
    @if (session()->has('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-bold flex items-center justify-between shadow-sm">
            <span class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </span>
        </div>
    @endif
    @if ($showQuizLibrary)
            <div class="bg-white border border-zinc-200 rounded-2xl shadow-sm p-6">
                <h2 class="text-sm font-black uppercase tracking-wider text-zinc-800 mb-4">My Quizzes</h2>
                @forelse ($this->myQuizzes as $quiz)
                    <div class="border-b border-zinc-100 last:border-b-0" wire:key="lib-quiz-{{ $quiz->id }}">
                        <button type="button" wire:click="toggleQuizDetails({{ $quiz->id }})" class="w-full flex items-center justify-between py-3 text-left cursor-pointer border-none bg-transparent">
                            <div>
                                <p class="font-bold text-zinc-900 text-sm">{{ $quiz->title }}</p>
                                <p class="text-xs text-zinc-400">
                                    {{ $quiz->group->name ?? $quiz->group->groupName ?? 'No group' }}
                                    &middot; {{ $quiz->questions->count() }} question(s)
                                    &middot; {{ $quiz->attempts_count }} attempt(s)
                                    @if($quiz->start_time) &middot; Scheduled for {{ $quiz->start_time->format('M d, Y H:i') }} @endif
                                </p>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $quiz->status === 'PUBLISHED' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-zinc-100 text-zinc-500 border-zinc-200' }} border">{{ $quiz->status }}</span>
                        </button>

                        @if($expandedQuizId === $quiz->id)
                            <div class="pb-4 flex flex-col gap-3">
                                @forelse($quiz->questions as $i => $question)
                                    <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3.5 text-xs" wire:key="lib-q-{{ $question->id }}">
                                        <p class="font-bold text-zinc-800 mb-2">{{ $i + 1 }}. {{ $question->text }}</p>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            @foreach(['A', 'B', 'C', 'D'] as $letter)
                                                <span class="px-2 py-1 rounded-lg border {{ $question->correct_answer === $letter ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold' : 'bg-white border-zinc-200 text-zinc-600' }}">
                                                    {{ $letter }}. {{ $question->options[$letter] ?? '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <p class="text-[10px] text-zinc-400 mt-1.5">{{ $question->points }} point(s)</p>
                                    </div>
                                @empty
                                    <p class="text-xs text-zinc-400 italic">No questions on this quiz.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-zinc-400 italic">You haven't created any quizzes yet.</p>
                @endforelse
            </div>
        @endif

    <form wire:submit.prevent="publishQuiz" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT PANEL: Quiz Configuration (4 out of 12 columns) -->
        <div class="lg:col-span-4 flex flex-col gap-6">
            <div class="bg-white p-6 rounded-2xl border border-zinc-200 shadow-sm flex flex-col gap-5">
                <div class="border-b border-zinc-100 pb-3">
                    <h2 class="text-sm font-black uppercase tracking-wider text-zinc-800">Quiz Settings</h2>
                </div>

                <!-- Input: Title -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Assessment Title</label>
                    <input wire:model="title" type="text" placeholder="e.g., Mid-Term Examination" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required />
                    @error('title') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                </div>

                <!-- Input: Description -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Description / Instructions</label>
                    <textarea wire:model="description" rows="3" placeholder="Provide instructions for the students..." class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required></textarea>
                    @error('description') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                </div>

                <!-- Input: Group Selection -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Target Study Group</label>
                    <select wire:model="group_id" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required>
                        @foreach($availableGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name ?? $group->groupName }}</option>
                        @endforeach
                    </select>
                    @error('group_id') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                </div>

                <!-- Input: Grid for Time and Status -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Time Limit (Min)</label>
                        <input wire:model="time_limit" type="number" min="1" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Status</label>
                        <select wire:model="status" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required>
                            <option value="DRAFT">Draft</option>
                            <option value="PUBLISHED">Published</option>
                        </select>
                    </div>
                </div>

                <!-- Input: Start Time -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-600 uppercase tracking-wider">Scheduled Start Time (Optional)</label>
                    <input wire:model="start_time" type="datetime-local" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" />
                </div>
            </div>
            
            <!-- Submit Button (Using your custom green) -->
            <button type="submit" class="w-full bg-[#24a065] hover:bg-[#1c7d4e] text-white font-black text-sm px-6 py-3.5 rounded-xl border-none shadow-sm transition-colors flex justify-center items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Deploy Assessment
            </button>
            
            @if($errors->has('questions.*') || $errors->has('questions'))
                <div class="p-3 bg-red-50 text-red-700 rounded-xl border border-red-200 text-xs font-bold">
                    Please ensure all question statements and options (A-D) are completely filled out.
                </div>
            @endif
        </div>

        <!-- RIGHT PANEL: Question Builder Matrix (8 out of 12 columns) -->
        <div class="lg:col-span-8 flex flex-col gap-4">
            <div class="bg-white border border-zinc-200 p-4 rounded-xl shadow-sm flex justify-between items-center sticky top-4 z-10">
                <h2 class="text-sm font-black uppercase tracking-wider text-zinc-800">Questions Matrix</h2>
                <button wire:click.prevent="addQuestion" type="button" class="bg-[#24a065] hover:bg-[#1c7d4e] text-white font-bold text-xs px-4 py-2 rounded-lg transition-colors shadow-sm">
                    + Add New Question
                </button>
            </div>

            <!-- Scrollable Questions Container -->
            <div class="flex flex-col gap-4 max-h-[75vh] overflow-y-auto pr-2 pb-10">
                @foreach($questions as $index => $q)
                    <div wire:key="q-key-{{ $index }}" class="bg-white rounded-2xl p-6 border border-zinc-200 shadow-sm flex flex-col gap-5 relative group transition-all focus-within:ring-2 focus-within:ring-[#52c48a] focus-within:border-transparent">
                        
                        <!-- Question Header -->
                        <div class="flex justify-between items-center border-b border-zinc-100 pb-3">
                            <span class="text-xs font-black uppercase text-zinc-500">Question #{{ $index + 1 }}</span>
                            @if(count($questions) > 1)
                                <button wire:click.prevent="removeQuestion({{ $index }})" type="button" class="text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition-colors">
                                    Remove
                                </button>
                            @endif
                        </div>
                        
                        <!-- Question Text -->
                        <div class="flex flex-col gap-1.5">
                            <textarea wire:model="questions.{{ $index }}.text" rows="2" placeholder="Enter the question statement..." class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg p-3 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all font-medium" required></textarea>
                        </div>


                        <!-- Options Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach(['A', 'B', 'C', 'D'] as $optLetter)
                                <div wire:key="q-{{ $index }}-opt-{{ $optLetter }}" class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-zinc-100 border border-zinc-200 text-xs font-black text-zinc-600 shrink-0">
                                        {{ $optLetter }}
                                    </div>
                                    <input wire:model="questions.{{ $index }}.options.{{ $optLetter }}" type="text" placeholder="Option {{ $optLetter }}" class="w-full bg-zinc-50 text-sm border border-zinc-300 rounded-lg p-2 focus:ring-2 focus:ring-[#52c48a] focus:border-[#52c48a] focus:outline-none transition-all" required />
                                </div>
                            @endforeach
                        </div>

                        <!-- Correct Answer & Points Setup -->
                        <div class="flex items-center gap-6 pt-2">
                            <div class="flex items-center gap-2 bg-[#52c48a]/10 px-3 py-2 rounded-lg border border-[#52c48a]/30">
                                <label class="text-xs font-bold text-[#1c7d4e] uppercase">Correct Answer:</label>
                                <select wire:model="questions.{{ $index }}.correct_answer" class="bg-white border border-[#52c48a] text-[#1c7d4e] text-xs font-bold rounded p-1 focus:outline-none focus:ring-1 focus:ring-[#24a065]">
                                    <option value="A">Option A</option>
                                    <option value="B">Option B</option>
                                    <option value="C">Option C</option>
                                    <option value="D">Option D</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2 bg-zinc-50 px-3 py-2 rounded-lg border border-zinc-200">
                                <label class="text-xs font-bold text-zinc-600 uppercase">Points:</label>
                                <input wire:model="questions.{{ $index }}.points" type="number" min="1" class="w-16 bg-white border border-zinc-300 text-zinc-900 text-xs font-bold rounded p-1 text-center focus:outline-none focus:ring-2 focus:ring-[#52c48a]" required />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </form>
</div>