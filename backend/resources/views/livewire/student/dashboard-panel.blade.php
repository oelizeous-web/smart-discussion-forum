<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Group;
use App\Models\Quiz;
use App\Models\Post;
use Carbon\Carbon;

new class extends Component {
    // Stat Cards
    public $activeGroupsCount = 0;
    public $pendingQuizzesCount = 0;
    public $avgScore = 0; 
    public $unreadMsgsCount = 0;
    public $platformWarnings = 0;
    public $blacklistWarnings = 0;

    // Collections for UI sections
    public $myGroups = [];
    public $upcomingQuizzes = [];
    public $recentActivities = [];

    // Auto-Popup Variables
    public $msUntilQuiz = 0;
    public $nextQuizId = null;

    public function mount()
    {
        $user = Auth::user();

            $this->platformWarnings = $user->warning_count;
            $this->blacklistWarnings = $user->blacklist->warning_count ?? 0;

        // 1. Fetch My Groups FIRST so $this->myGroups becomes a Collection
        $this->myGroups = Group::where('user_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->take(4)
            ->get();

        $this->activeGroupsCount = $this->myGroups->count();

        // 2. Fetch Pending Quizzes
        if ($this->activeGroupsCount > 0) {
            $groupIds = $this->myGroups->pluck('id');
            
            $pendingQuery = Quiz::whereIn('group_id', $groupIds)
                ->where('status', '!=', 'DRAFT')
                ->whereDoesntHave('attempts', function($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->whereNotNull('submitted_at');
                });

            $this->pendingQuizzesCount = $pendingQuery->count();
            
            $this->upcomingQuizzes = $pendingQuery
                ->orderBy('start_time', 'asc')
                ->take(3)
                ->get();

            // === QUIZ POPUP LOGIC ===
            $nextQuiz = $this->upcomingQuizzes->firstWhere('start_time', '!=', null);
            if ($nextQuiz) {
                $this->nextQuizId = $nextQuiz->id;
                $this->msUntilQuiz = now()->diffInMilliseconds(Carbon::parse($nextQuiz->start_time), false);
            }
        }

        // 3. Average Score
        $average = DB::table('marks')
            ->where('user_id', $user->id)
            ->avg('score');
            
        $this->avgScore = $average ? round($average, 1) : 0;

        // 4. Unread messages (reuse group IDs directly)
        if ($this->activeGroupsCount > 0) {
            $myGroupIds = $this->myGroups->pluck('id');

            $this->unreadMsgsCount = Post::where('created_at', '>=', now()->subDays(7))
                ->where('user_id', '!=', $user->id)
                ->whereHas('topic', fn ($q) => $q->whereIn('group_id', $myGroupIds))
                ->count();
        }

        // 5. Recent Activity Placeholder
        $this->recentActivities = collect([]); 
    }
}; ?>

<style>
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }
</style>

<div class="p-4 sm:p-6 space-y-6 sm:space-y-8 min-h-screen bg-[#F7F5EE] text-zinc-900">

    <!-- Page Header & Action -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <h2 class="text-xl sm:text-2xl font-bold tracking-wide text-zinc-900">Dashboard Overview</h2>
        <button id="join-group-btn" @click="$dispatch('open-join-modal')" class="w-full sm:w-auto justify-center bg-[#2F7A54] hover:bg-[#256242] text-white px-4 py-2 rounded-lg font-semibold flex items-center transition-colors shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Join a Group
        </button>
    </div>

    @if($platformWarnings > 0 || $blacklistWarnings > 0)
    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r-lg shadow-sm">
        <div class="flex items-center">
            <svg class="h-6 w-6 text-orange-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h3 class="text-orange-700 font-bold text-sm">Account Warning ({{ $platformWarnings }}/3 Strikes)</h3>
                <p class="text-orange-700/80 text-sm mt-1">
                    You have received warnings for platform inactivity or inappropriate behavior. Reach 3 strikes, and your account will be suspended.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- 1. STAT CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
        <!-- Active Groups -->
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center text-zinc-500">
                <span class="text-xs font-semibold uppercase tracking-wider">Active Groups</span>
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <p class="text-3xl font-bold text-zinc-900 mt-4">{{ $activeGroupsCount }}</p>
        </div>

        <!-- Pending Quizzes -->
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center text-zinc-500">
                <span class="text-xs font-semibold uppercase tracking-wider">Pending Quizzes</span>
                <svg class="w-4 h-4 text-[#2F7A54]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            </div>
            <p class="text-3xl font-bold text-zinc-900 mt-4">{{ $pendingQuizzesCount }}</p>
        </div>

        <!-- Avg. Score -->
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center text-zinc-500">
                <span class="text-xs font-semibold uppercase tracking-wider">Avg. Score</span>
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <p class="text-3xl font-bold text-zinc-900 mt-4">{{ $avgScore > 0 ? $avgScore . '%' : '-' }}</p>
        </div>

        <!-- Unread Msgs -->
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center text-zinc-500">
                <span class="text-xs font-semibold uppercase tracking-wider">Unread Msgs (7days)</span>
                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </div>
            <p class="text-3xl font-bold text-zinc-900 mt-4">{{ $unreadMsgsCount }}</p>
        </div>
    </div>

    <!-- 2. MAIN PANELS (Grid Layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- MY GROUPS CONTAINER -->
        <div class="lg:col-span-2">
            <h2 class="text-lg font-bold text-zinc-900 mb-4">MY GROUPS</h2>

            <!-- This container now has a fixed height and hides the scrollbar -->
            <div class="h-[300px] overflow-y-auto no-scrollbar space-y-4 pr-2">
                @forelse($myGroups as $group)
                    <div class="bg-white border border-zinc-200 p-5 rounded-xl shadow-sm flex justify-between items-center shrink-0">
                        <div>
                            <h3 class="text-zinc-900 font-bold">{{ $group->name }}</h3>
                            <p class="text-zinc-500 text-sm">Group ID: {{ $group->id }}</p>
                        </div>
                    </div>
                @empty
                    <div class="border border-dashed border-zinc-300 rounded-xl p-10 flex items-center justify-center text-center h-[300px]">
                        <p class="text-zinc-400">You haven't joined any groups yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Quizzes -->
        <div class="bg-white border border-zinc-200 rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-bold tracking-wide uppercase text-zinc-900 mb-4">Upcoming Quizzes</h3>
            <div class="space-y-3">
                @forelse($upcomingQuizzes as $quiz)
                    <div class="bg-zinc-50 border border-zinc-200 p-4 rounded-lg hover:bg-zinc-100 transition-colors cursor-pointer">
                        <h4 class="text-zinc-900 font-medium truncate">{{ $quiz->title }}</h4>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-zinc-500">
                                {{ $quiz->start_time ? \Carbon\Carbon::parse($quiz->start_time)->diffForHumans() : 'No start time set' }}
                            </span>
                            <span class="text-xs bg-green-50 text-[#2F7A54] border border-green-200 px-2 py-0.5 rounded-full font-medium">Pending</span>
                        </div>
                    </div>
                @empty
                    <div class="border border-dashed border-zinc-300 rounded-lg p-10 flex items-center justify-center text-center h-32">
                        <p class="text-zinc-400">No pending quizzes.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 3. RECENT ACTIVITY -->
    <div class="bg-white border border-zinc-200 rounded-xl shadow-sm p-6">
        <h3 class="text-sm font-bold tracking-wide uppercase text-zinc-900 mb-4">Recommended topics</h3>
        <div class="space-y-4">
            @forelse($recentActivities as $activity)
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center text-[#2F7A54] shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm text-zinc-700">{!! $activity->description !!}</p>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="text-zinc-400 italic text-sm">Activity log is empty.</p>
            @endforelse
        </div>
    </div>
    
    <livewire:student.join-group-modal />

    <!-- === FULL SCREEN QUIZ POP-UP LOGIC === -->
    @if($nextQuizId)
        <div id="quiz-popup" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center;">
            <div style="background: white; padding: 40px; border-radius: 12px; text-align: center; max-width: 450px; box-shadow: 0 15px 30px rgba(0,0,0,0.5);">
                <h2 style="color: #e3342f; font-size: 22px; font-weight: bold; margin-bottom: 10px;">🚨 Quiz is Now Open! 🚨</h2>
                <p style="color: #4a5568; font-size: 15px; margin-bottom: 20px;">The scheduled time has arrived. You must enter the quiz now.</p>
                
                <a href="/student/quizzes/{{ $nextQuizId }}" 
                   style="background: #e3342f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                   Take Quiz Now
                </a>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const msUntilQuiz = @json($msUntilQuiz);

                // If a quiz is scheduled in the future (within 24 hours)
                if (msUntilQuiz > 0 && msUntilQuiz < 86400000) {
                    setTimeout(() => {
                        // Instantly force the pop-up to show on screen when time hits zero
                        document.getElementById('quiz-popup').style.display = 'flex';
                    }, msUntilQuiz);
                } 
                // If the user loads the page *after* the quiz has already started (within the last hour)
                else if (msUntilQuiz <= 0 && msUntilQuiz > -3600000) {
                    document.getElementById('quiz-popup').style.display = 'flex';
                }
            });
        </script>
    @endif
</div>