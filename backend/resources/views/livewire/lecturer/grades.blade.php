<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Group;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $groups = [];
    public $selectedGroupId = null;
    public $selectedQuizId = 'all';
    public $search = '';

    // Modal state for viewing a specific student's complete assessment profile
    public bool $showStudentModal = false;
    public ?array $activeStudentProfile = null;

    // System benchmarks for the modern floating reference panel
    public array $gradingScale = [
        ['grade' => 'A', 'range' => '80% - 100%', 'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50'],
        ['grade' => 'B', 'range' => '70% - 79%', 'badge' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-400 border-blue-200 dark:border-blue-800/50'],
        ['grade' => 'C', 'range' => '60% - 69%', 'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-800/50'],
        ['grade' => 'D', 'range' => '50% - 59%', 'badge' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-400 border-orange-200 dark:border-orange-800/50'],
        ['grade' => 'F', 'range' => '0% - 49%', 'badge' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-400 border-red-200 dark:border-red-800/50'],
    ];

    public function mount()
    {
        $this->groups = Group::all();
        
        if ($this->groups->isNotEmpty()) {
            $this->selectedGroupId = $this->groups->first()->id;
        }
    }

    public function updatedSelectedGroupId()
    {
        $this->selectedQuizId = 'all';
        $this->resetPage();
    }

    public function updatedSelectedQuizId()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * Dynamic Data Provider: Explicitly queries the database (`marks`, `quizzes`, `questions`, `quiz_attempts`)
     * to pull exact preconfigured student evaluations and assessment marks (Read-Only).
     */
    public function with(): array
    {
        $studentsPaginator = collect();
        $groupQuizzes = collect();

        if ($this->selectedGroupId) {
            $group = Group::find($this->selectedGroupId);

            if ($group) {
                // 1. Fetch preconfigured quizzes belonging strictly to this group
                $groupQuizzes = DB::table('quizzes')
                    ->where('group_id', $group->id)
                    ->get();

                $groupQuizIds = $groupQuizzes->pluck('id');

                // 2. Base query for members inside this group
                $query = $group->members();

                if (!empty($this->search)) {
                    $query->where(function($q) {
                        $q->where('users.name', 'like', '%' . $this->search . '%')
                          ->orWhere('users.email', 'like', '%' . $this->search . '%');
                    });
                }

                $paginatedMembers = $query->paginate(8);

                // 3. Map members with their actual saved records fetched straight from the `marks` table
                $transformedItems = collect($paginatedMembers->items())->map(function($member) use ($groupQuizzes, $groupQuizIds) {
                    
                    // Determine which quiz target we are evaluating
                    $targetQuizId = null;
                    $quizTitle = 'General Assessment Ledger';

                    if ($this->selectedQuizId !== 'all') {
                        $targetQuizId = $this->selectedQuizId;
                        $quizTitle = DB::table('quizzes')->where('id', $targetQuizId)->value('title') ?? 'Assessment';
                    } elseif ($groupQuizzes->isNotEmpty()) {
                        // Default to the first preconfigured quiz in this group if 'all' is selected
                        $targetQuizId = $groupQuizzes->first()->id;
                        $quizTitle = $groupQuizzes->first()->title;
                    }

                    // Query the exact database mark record
                    $markRecord = null;
                    if ($targetQuizId) {
                        $markRecord = DB::table('marks')
                            ->where('user_id', $member->id)
                            ->where('quiz_id', $targetQuizId)
                            ->first();
                    } elseif ($groupQuizIds->isNotEmpty()) {
                        $markRecord = DB::table('marks')
                            ->where('user_id', $member->id)
                            ->whereIn('quiz_id', $groupQuizIds)
                            ->first();
                    }

                    // Retrieve exact score or compute it dynamically if questions are preconfigured
                    $score = 0;
                    if ($markRecord) {
                        $score = (int)$markRecord->score;
                        $targetQuizId = $markRecord->quiz_id;
                        $quizTitle = DB::table('quizzes')->where('id', $markRecord->quiz_id)->value('title') ?? $quizTitle;
                    } else {
                        // Fallback check against quiz_attempts or preconfigured question weights if marks row is missing
                        $attempt = DB::table('quiz_attempts')
                            ->where('user_id', $member->id)
                            ->when($targetQuizId, fn($q) => $q->where('quiz_id', $targetQuizId))
                            ->latest('submitted_at')
                            ->first();

                        if ($attempt && !empty($attempt->answers)) {
                            // Compute score dynamically from preconfigured correct answers in questions table
                            $score = $this->calculateScoreFromAnswers($attempt->quiz_id, json_decode($attempt->answers, true));
                        }
                    }

                    $grade = $this->calculateGrade($score);

                    return [
                        'id' => 'STU' . str_pad($member->id, 3, '0', STR_PAD_LEFT),
                        'user_id' => $member->id,
                        'quiz_id' => $targetQuizId,
                        'name' => $member->name,
                        'email' => $member->email,
                        'quiz' => $quizTitle,
                        'score' => $score,
                        'total' => 100,
                        'grade' => $grade,
                    ];
                });

                // Reconstruct a custom paginator structure to preserve pagination behavior with mapped collections
                $studentsPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    $transformedItems,
                    $paginatedMembers->total(),
                    $paginatedMembers->perPage(),
                    $paginatedMembers->currentPage(),
                    ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
                );
            }
        }

        return [
            'students' => $studentsPaginator,
            'groupQuizzes' => $groupQuizzes
        ];
    }

    /**
     * Computes the score dynamically by comparing user answers against preconfigured question correct_answers.
     */
    private function calculateScoreFromAnswers($quizId, $userAnswers): int
    {
        $questions = DB::table('questions')->where('quiz_id', $quizId)->get();
        if ($questions->isEmpty()) return 0;

        $totalPointsEarned = 0;
        $totalPossiblePoints = 0;

        foreach ($questions as $question) {
            $totalPossiblePoints += $question->points;
            $userAnswerKey = $userAnswers[$question->id] ?? null;

            if ($userAnswerKey && trim(strtoupper($userAnswerKey)) === trim(strtoupper($question->correct_answer))) {
                $totalPointsEarned += $question->points;
            }
        }

        if ($totalPossiblePoints === 0) return 0;
        return (int) round(($totalPointsEarned / $totalPossiblePoints) * 100);
    }

    private function calculateGrade(int $score): string
    {
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    /**
     * Comprehensive Student Assessment Profile Inspector Modal.
     * Pulls precise historical marks from the `marks` and `quiz_attempts` database tables.
     */
    public function viewStudentProfile($userId)
    {
        $user = User::find($userId);
        if (!$user) return;

        // Fetch exact records from the database tables
        $allMarks = DB::table('marks')
            ->join('quizzes', 'marks.quiz_id', '=', 'quizzes.id')
            ->where('marks.user_id', $userId)
            ->select('marks.score', 'quizzes.title', 'quizzes.time_limit', 'marks.updated_at')
            ->get();

        $quizAttempts = DB::table('quiz_attempts')
            ->join('quizzes', 'quiz_attempts.quiz_id', '=', 'quizzes.id')
            ->where('quiz_attempts.user_id', $userId)
            ->select('quizzes.title', 'quiz_attempts.start_time', 'quiz_attempts.submitted_at', 'quiz_attempts.auto_submitted')
            ->get();

        $this->activeStudentProfile = [
            'name' => $user->name,
            'email' => $user->email,
            'id' => 'STU' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'marks' => $allMarks,
            'attempts' => $quizAttempts,
            'average_score' => $allMarks->isNotEmpty() ? round($allMarks->avg('score'), 1) : 0,
        ];

        $this->showStudentModal = true;
    }

    public function closeModal()
    {
        $this->showStudentModal = false;
        $this->activeStudentProfile = null;
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 bg-zinc-50 dark:bg-zinc-950 min-h-screen text-zinc-900 dark:text-zinc-100 transition-colors duration-200">
    
    <!-- Header Section & Global Controls Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 rounded-2xl shadow-sm">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">Grades Ledger & Database Matrix</h1>
            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 mt-1">Review live student database performance logs, evaluate preconfigured assessment answers, and audit individual student records.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-4">
            <!-- Group Selector -->
            @if($groups->isNotEmpty())
                <div class="w-full sm:w-56">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1">Target Study Group</label>
                    <select wire:model.live="selectedGroupId" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all">
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name ?? $group->groupName }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <!-- Quiz Filter Selector -->
            <div class="w-full sm:w-56">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1">Filter by Assessment</label>
                <select wire:model.live="selectedQuizId" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all">
                    <option value="all">All Group Assessments</option>
                    @foreach($groupQuizzes as $quiz)
                        <option value="{{ $quiz->id }}">{{ $quiz->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Main Workspace Layout Stack -->
    <div class="space-y-6">
        
        <!-- Grading Scale Reference Bar -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Database-Backed Q&A Scoring & Grading Benchmarks</span>
                </div>
                <div class="grid grid-cols-2 sm:flex sm:items-center gap-3">
                    @foreach($gradingScale as $scale)
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border {{ $scale['badge'] }} shadow-xs">
                            <span class="text-xs font-black">{{ $scale['grade'] }}</span>
                            <span class="text-[11px] font-mono font-bold">{{ $scale['range'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Search Bar and Count Header -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 rounded-xl shadow-sm">
            <div class="w-full sm:w-80 relative">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search student by name..." class="w-full pl-10 pr-4 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
            </div>
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                Displaying roster records with <span class="text-zinc-900 dark:text-white font-bold">pagination enabled</span> to prevent infinite scrolling.
            </div>
        </div>

        <!-- Primary Workspace Table Card with Bounded Height & Pagination -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                    <thead class="bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        <tr>
                            <th class="p-4">Student Details</th>
                            <th class="p-4">Assessment Target</th>
                            <th class="p-4 text-center w-40">Database Mark Score</th>
                            <th class="p-4 text-center w-32">Calculated Grade</th>
                            <th class="p-4 text-right w-40">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 font-medium text-zinc-700 dark:text-zinc-300">
                        @forelse($students as $index => $row)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-950/50 transition-colors" wire:key="grade-row-{{ $row['id'] }}-{{ $row['user_id'] }}">
                            <!-- Student Metadata -->
                            <td class="p-4">
                                <span class="block font-bold text-zinc-900 dark:text-white text-sm">{{ $row['name'] }}</span>
                                <span class="block text-[11px] font-mono text-zinc-400 mt-0.5">{{ $row['email'] }}</span>
                            </td>
                            
                            <!-- Quiz Title Identifier -->
                            <td class="p-4 text-zinc-600 dark:text-zinc-400 font-semibold">
                                {{ $row['quiz'] }}
                            </td>
                            
                            <!-- DATABASE-BACKED SCORE (READ-ONLY) -->
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5 mx-auto max-w-[120px]">
                                    <span class="w-16 block text-center bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg py-1.5 font-black text-sm text-zinc-900 dark:text-zinc-100 shadow-xs">
                                        {{ $row['score'] }}
                                    </span>
                                    <span class="text-zinc-400 font-bold text-xs">/100</span>
                                </div>
                            </td>
                            
                            <!-- AUTOMATIC BADGE -->
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center justify-center w-9 py-1 text-xs font-black rounded-lg shadow-xs border transition-all duration-200
                                    {{ $row['grade'] === 'A' ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800' : '' }}
                                    {{ $row['grade'] === 'B' ? 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/80 dark:text-blue-300 dark:border-blue-800' : '' }}
                                    {{ $row['grade'] === 'C' ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/80 dark:text-amber-300 dark:border-amber-800' : '' }}
                                    {{ $row['grade'] === 'D' ? 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/80 dark:text-orange-300 dark:border-orange-800' : '' }}
                                    {{ $row['grade'] === 'F' ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/80 dark:text-red-300 dark:border-red-800' : '' }}
                                ">
                                    {{ $row['grade'] }}
                                </span>
                            </td>

                            <!-- ACTION: View Individual Student Performance Profile -->
                            <td class="p-4 text-right">
                                <button wire:click="viewStudentProfile({{ $row['user_id'] }})" class="inline-flex items-center justify-center px-3 py-1.5 bg-zinc-100 hover:bg-emerald-600 hover:text-white dark:bg-zinc-800 dark:hover:bg-emerald-600 text-zinc-700 dark:text-zinc-300 font-semibold rounded-lg transition-colors text-xs">
                                    View Full Profile
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-12 text-center text-zinc-400 italic">
                                No student assessment records found for this active group filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Container Links -->
            @if(method_exists($students, 'hasPages') && $students->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950">
                    {{ $students->links() }}
                </div>
            @endif
        </div>

    </div>

    <!-- STUDENT COMPLETE ASSESSMENT PROFILE MODAL -->
    @if($showStudentModal && $activeStudentProfile)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/60 backdrop-blur-sm p-4 animate-fade-in">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden transform transition-all">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-black text-zinc-900 dark:text-white">{{ $activeStudentProfile['name'] }}'s Assessment Record</h3>
                        <p class="text-xs font-mono text-zinc-500 mt-0.5">{{ $activeStudentProfile['id'] }} • {{ $activeStudentProfile['email'] }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body Content -->
                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                    
                    <!-- Overview Stat Bar -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Overall Database Average Score</span>
                            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $activeStudentProfile['average_score'] }}%</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Total Assessments Recorded</span>
                            <p class="text-2xl font-black text-zinc-900 dark:text-white mt-1">{{ count($activeStudentProfile['marks']) }}</p>
                        </div>
                    </div>

                    <!-- Complete Marks Table for Every Assessment -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-black uppercase tracking-wider text-zinc-500">Database Marks Ledger Across All Assessments</h4>
                        
                        @if(count($activeStudentProfile['marks']) > 0)
                            <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-zinc-50 dark:bg-zinc-950 font-bold text-zinc-500 border-b border-zinc-200 dark:border-zinc-800">
                                        <tr>
                                            <th class="p-3">Quiz Title</th>
                                            <th class="p-3 text-center">Score Recorded</th>
                                            <th class="p-3 text-right">Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach($activeStudentProfile['marks'] as $mark)
                                            <tr>
                                                <td class="p-3 font-semibold text-zinc-900 dark:text-white">{{ $mark->title }}</td>
                                                <td class="p-3 text-center font-bold text-emerald-600">{{ $mark->score }} / 100</td>
                                                <td class="p-3 text-right text-zinc-400 font-mono text-[11px]">{{ \Carbon\Carbon::parse($mark->updated_at)->format('M d, Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-zinc-400 italic bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl text-center">No recorded marks found for this student in the database.</p>
                        @endif
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-950 border-t border-zinc-200 dark:border-zinc-800 flex justify-end">
                    <button wire:click="closeModal" class="px-5 py-2 bg-zinc-900 hover:bg-zinc-800 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-white font-bold text-xs rounded-xl transition-colors">
                        Close Profile
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>