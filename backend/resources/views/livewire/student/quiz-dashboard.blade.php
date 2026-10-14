<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Services\QuizService;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public function with(QuizService $quizService): array
    {
        $user = Auth::user();
        
        // Fetch all quiz IDs the user has already marked
        $completedQuizIds = \App\Models\Mark::where('user_id', $user->id)
            ->pluck('quiz_id')
            ->toArray();

        return [
            'upcomingQuizzes' => $quizService->getAvailableQuizzes(),
            'stats' => $quizService->getUserStats($user),
            'recentResults' => $quizService->getRecentResults($user, 5),
            'completedQuizIds' => $completedQuizIds, // Pass this to the view
        ];
    }
}; ?>

<div class="p-4 sm:p-8 max-w-7xl mx-auto min-h-screen bg-[#F7F5EE]">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-2 mb-8 border-b border-zinc-200 pb-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-zinc-900 tracking-wide">Quiz Center</h1>
            <p class="text-zinc-500 mt-1">Test your knowledge and track your progress.</p>
        </div>
    </div>

    <!-- Flash Message for successful submission -->
    @if (session()->has('success'))
        <div class="mb-8 p-4 bg-green-50 border border-green-200 rounded-xl text-[#2F7A54] font-medium flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-10">
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm">
            <div class="text-zinc-500 text-sm font-semibold uppercase tracking-wider mb-2">Quizzes Completed</div>
            <div class="text-4xl font-bold text-zinc-900">{{ $stats['completed_count'] }}</div>
        </div>
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm">
            <div class="text-zinc-500 text-sm font-semibold uppercase tracking-wider mb-2">Average Score</div>
            <div class="text-4xl font-bold text-[#2F7A54]">{{ $stats['average_score'] }}</div>
        </div>
        <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm">
            <div class="text-zinc-500 text-sm font-semibold uppercase tracking-wider mb-2">Pending Reviews</div>
            <div class="text-4xl font-bold text-yellow-600">{{ $stats['pending_reviews'] }}</div>
        </div>
    </div>

    <!-- Two-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Main Content: Available Quizzes -->
        <div class="lg:col-span-2 space-y-6">
            <h2 class="text-xl font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Available Now
            </h2>

            <div class="grid gap-4">
                @forelse($upcomingQuizzes as $quiz)
                @php
                    $isUpcoming = $quiz->start_time && now()->isBefore($quiz->start_time);
                    $isExpired  = $quiz->hasClosed();
                    $isCompleted = in_array($quiz->id, $completedQuizIds);
                @endphp

                    <div class="bg-white hover:bg-zinc-50 border border-zinc-200 p-6 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center transition duration-200 shadow-sm">
                        <div class="mb-4 sm:mb-0">
                            <h3 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
                                {{ $quiz->title }}
                                @if($isUpcoming)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-orange-600 border border-orange-200">
                                        Locked
                                    </span>
                                @endif
                            </h3>
                            <div class="flex gap-4 mt-2 text-sm text-zinc-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    {{ $quiz->group->name ?? 'General' }}
                                </span>
                                
                                @if($isUpcoming)
                                    <span class="flex items-center gap-1 text-orange-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Opens {{ \Carbon\Carbon::parse($quiz->start_time)->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Updated Conditional Button Display -->
                        @if($isUpcoming)
                            <button disabled class="px-6 py-2 bg-zinc-100 text-zinc-400 font-semibold rounded-lg cursor-not-allowed border border-zinc-200">
                                Not Open Yet
                            </button>
                        @elseif($isExpired && !$isCompleted)
                            <button disabled class="px-6 py-2 bg-red-50 text-red-400 font-semibold rounded-lg cursor-not-allowed border border-red-200">
                                Expired
                            </button>
                        @elseif($isCompleted)
                            <button disabled class="px-6 py-2 bg-zinc-50 text-zinc-400 font-semibold rounded-lg border border-zinc-200 cursor-not-allowed">
                                Completed
                            </button>
                        @else
                            <a href="{{ route('quiz.attempt', ['id' => $quiz->id]) }}" class="px-6 py-2 bg-[#2F7A54] hover:bg-[#256242] text-white font-semibold rounded-lg shadow-sm transition">
                                Start Quiz
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="bg-white border border-zinc-200 border-dashed p-12 rounded-xl text-center">
                        <svg class="w-12 h-12 text-zinc-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        <p class="text-zinc-500 font-medium">You're all caught up!</p>
                        <p class="text-zinc-400 text-sm mt-1">No new quizzes have been assigned to your groups.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            
            <!-- Dynamic Recent Results Section -->
            <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm">
                <h3 class="text-zinc-900 font-bold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#2F7A54]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Recent Results
                </h3>
                
                @if($recentResults->isEmpty())
                    <p class="text-sm text-zinc-400 italic">Complete a quiz to see your history here.</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentResults as $result)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 border border-zinc-200">
                                <div>
                                    <div class="text-sm font-bold text-zinc-800 truncate max-w-[150px]" title="{{ $result->quiz->title }}">
                                        {{ $result->quiz->title }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 uppercase tracking-wider mt-0.5">
                                        {{ $result->updated_at->format('M d, Y') }}
                                    </div>
                                </div>
                                <div class="px-2.5 py-1 rounded-md font-bold text-sm border 
                                    {{ $result->score >= 75 ? 'bg-green-50 text-[#2F7A54] border-green-200' : 
                                      ($result->score >= 50 ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 
                                      'bg-red-50 text-red-600 border-red-200') }}">
                                    {{ $result->score }}%
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Rules Box -->
            <div class="bg-white border border-zinc-200 p-6 rounded-xl shadow-sm">
                <h3 class="text-zinc-900 font-bold mb-4">Quiz Rules & Integrity</h3>
                <ul class="space-y-3 text-sm text-zinc-500">
                    <li class="flex items-start gap-2">
                        <span class="text-red-500 mt-0.5">•</span>
                        Quizzes are timed. The timer cannot be paused once started.
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-500 mt-0.5">•</span>
                        Navigating away from the quiz page may auto-submit your answers.
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-500 mt-0.5">•</span>
                        Scores are final upon submission.
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>