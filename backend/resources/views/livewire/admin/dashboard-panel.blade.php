<?php
use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ModerationService;

new class extends Component {
    public $groups = [];
    public $selectedGroupId = null;
    
    // UI State for Group Statistics
    public $totalPosts = 0;
    public $activeMembers = 0;
    public $totalMembers = 0;
    public $quizParticipationRate = 0;
    public $topContributor = 'N/A';
    public $students = []; 

    public function mount()
    {
        $this->groups = Group::all(); 
        
        if ($this->groups->isNotEmpty()) {
            $this->selectedGroupId = $this->groups->first()->id;
            $this->loadGroupStatistics();
        }
    }

    // Runs automatically when the user toggles the dropdown
    public function updatedSelectedGroupId()
    {
        $this->loadGroupStatistics();
    }

    public function loadGroupStatistics()
    {
        // Eager load members + their blacklist record (source of truth for status)
        $group = Group::with(['members.blacklist'])->find($this->selectedGroupId);
        
        if ($group) {
            // 1. Total Posts
            $this->totalPosts = DB::table('posts')
                ->join('topics', 'posts.topic_id', '=', 'topics.id')
                ->where('topics.group_id', $group->id)
                ->count();

            // 2. Member Activeness (status lives on the blacklist relation, not the pivot)
            $this->totalMembers = $group->members->count();
            $this->activeMembers = $group->members->filter(function($member) {
                return ($member->blacklist->status ?? 'ACTIVE') === 'ACTIVE';
            })->count();
            
            // 3. Top Contributor
            $topMember = $group->members()
                ->withCount(['posts' => function ($query) use ($group) {
                    $query->whereHas('topic', function ($q) use ($group) {
                        $q->where('group_id', $group->id);
                    });
                }])
                ->orderByDesc('posts_count')
                ->first();
                
            $this->topContributor = ($topMember && $topMember->posts_count > 0) 
                ? $topMember->name 
                : 'N/A';

            // 4. Quiz Participation Rate (FIXED: Now uses 'marks' instead of 'quiz_user')
            $totalQuizzes = DB::table('quizzes')->where('group_id', $group->id)->count();
            if ($totalQuizzes > 0 && $this->totalMembers > 0) {
                $participatedMembers = DB::table('marks')
                    ->join('quizzes', 'marks.quiz_id', '=', 'quizzes.id')
                    ->where('quizzes.group_id', $group->id)
                    ->distinct('marks.user_id')
                    ->count('marks.user_id');
                    
                $this->quizParticipationRate = round(($participatedMembers / $this->totalMembers) * 100);
            } else {
                $this->quizParticipationRate = 0;
            }

            // 5. Mapping Students for the Table (real columns: users.warning_count + blacklists.status)
            $this->students = $group->members->map(function($student) {
                $warnings = $student->warning_count ?? 0;
                $status = $student->blacklist->status ?? 'ACTIVE';
                if ($warnings == 1 && $status === 'ACTIVE') $status = 'WARNING SENT';
                if ($status === 'SUSPENDED') $status = 'BLACKLISTED';

                return (object) [
                    'id' => $student->id,
                    'name' => $student->name,
                    'last_seen' => $student->lastCommunicationDate()->diffForHumans(),
                    'warnings' => $warnings,
                    'status' => $status,
                ];
            });

            // 6. Dispatch Chart Data (Group Activity: Posts vs Quizzes)
            $chartData = $this->getWeeklyActivityData($group->id);
            $this->dispatch('update-chart-data', data: $chartData);
        } else {
            $this->resetStats();
        }
    }


    /**
     * Action: Lift ban and reset warnings for a student.
     */
    public function liftBan($userId, ModerationService $moderationService)
    {
        $moderationService->reinstateUser($userId);
        $this->loadGroupStatistics(); // unchanged
    }

    /**
     * Aggregates group activity (Posts vs Quiz Attempts) over the last 7 days.
     * Compatible for JSON serialization (Java UI team can consume via standard API).
     */
    private function getWeeklyActivityData($groupId)
    {
        $labels = [];
        $postsData = [];
        $quizzesData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d'); // e.g., "Jul 19"

            try {
                // Count posts made in this group on this date
                $postsData[] = DB::table('posts')
                    ->join('topics', 'posts.topic_id', '=', 'topics.id')
                    ->where('topics.group_id', $groupId)
                    ->whereDate('posts.created_at', $date->toDateString())
                    ->count();

                // Count quiz attempts in this group on this date (FIXED: Corrected assignment, table name, and date filter)
                $quizzesData[] = DB::table('marks')
                    ->join('quizzes', 'marks.quiz_id', '=', 'quizzes.id')
                    ->where('quizzes.group_id', $groupId)
                    ->whereDate('marks.created_at', $date->toDateString())
                    ->count(DB::raw('distinct marks.user_id'));
            } catch (\Exception $e) {
                // Fallback to 0 if tables are missing or queried prematurely
                $postsData[] = 0;
                $quizzesData[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'posts' => $postsData,
            'quizzes' => $quizzesData
        ];
    }

    private function resetStats()
    {
        $this->totalPosts = 0;
        $this->activeMembers = 0;
        $this->totalMembers = 0;
        $this->quizParticipationRate = 0;
        $this->topContributor = 'N/A';
        $this->students = [];
        $this->dispatch('update-chart-data', data: ['labels' => [], 'posts' => [], 'quizzes' => []]);
    }
}; ?>

<div class="p-4 sm:p-6 lg:p-8 space-y-8 bg-gray-50 min-h-screen text-gray-900 font-sans">
    
    <!-- Page Header & Group Selector -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">Dashboard & Analytics</h2>
            <p class="text-sm text-gray-500 mt-1">Overview of group engagement and member statistics</p>
        </div>
        
        @if($groups->isNotEmpty())
            <!-- Live wire binding triggers updatedSelectedGroupId() instantly -->
            <select wire:model.live="selectedGroupId" class="w-full sm:w-64 bg-white border border-gray-300 text-gray-700 rounded-xl px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-shadow cursor-pointer">
                @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name ?? $group->groupName }}</option>
                @endforeach
            </select>
        @else
            <span class="text-gray-500 italic text-sm">No groups available</span>
        @endif
    </div>

    <!-- 1. GROUP STATISTICS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Posts</p>
            <p class="text-3xl font-bold text-gray-900 mt-3">{{ number_format($totalPosts) }}</p>
            <p class="text-xs font-medium text-green-600 mt-2 bg-green-50 inline-block px-2 py-1 rounded-md">Group lifetime total</p>
        </div>
        
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Members</p>
            <div class="flex items-baseline gap-2 mt-3">
                <p class="text-3xl font-bold text-gray-900">{{ $activeMembers }}</p>
                <p class="text-lg font-medium text-gray-400">/ {{ $totalMembers }}</p>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $totalMembers - $activeMembers }} members flagged/inactive</p>
        </div>
        
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Quiz Participation</p>
            <p class="text-3xl font-bold text-gray-900 mt-3">{{ $quizParticipationRate }}%</p>
            <p class="text-xs text-gray-500 mt-2">Average completion rate</p>
        </div>
        
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Top Contributor</p>
            <p class="text-2xl font-bold text-green-600 mt-3 truncate" title="{{ $topContributor }}">{{ $topContributor }}</p>
            <p class="text-xs text-gray-500 mt-2">Highest post volume</p>
        </div>
    </div>

    <!-- 2. FULL-WIDTH ACTIVITY GRAPH -->
    <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm w-full flex flex-col">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h3 class="text-gray-900 font-bold tracking-tight text-lg">7-Day Group Activity</h3>
            <button class="text-sm font-medium bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl transition-colors border border-gray-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-200">
                Export to PDF
            </button>
        </div>
        <div class="relative w-full h-[350px]">
            <canvas id="activityChart" wire:ignore></canvas>
        </div>
    </div>
    
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Light theme default chart configurations
            Chart.defaults.color = '#6b7280'; // gray-500
            Chart.defaults.borderColor = '#f3f4f6'; // gray-100
            Chart.defaults.font.family = "'Inter', 'sans-serif'";

            const activityCtx = document.getElementById('activityChart').getContext('2d');

            // Creating subtle light-theme gradients for the line fills
            const gradientBlue = activityCtx.createLinearGradient(0, 0, 0, 400);
            gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.2)'); 
            gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.0)'); 

            const gradientPurple = activityCtx.createLinearGradient(0, 0, 0, 400);
            gradientPurple.addColorStop(0, 'rgba(168, 85, 247, 0.2)'); 
            gradientPurple.addColorStop(1, 'rgba(168, 85, 247, 0.0)'); 

            let accessChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: [], 
                    datasets: [
                        {
                            label: 'Posts Created',
                            data: [],
                            borderColor: '#3b82f6', // blue-500
                            backgroundColor: gradientBlue,
                            borderWidth: 2, 
                            fill: true, 
                            tension: 0.4, 
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#3b82f6',
                            pointBorderWidth: 2,
                            pointRadius: 4, 
                            pointHoverRadius: 6 
                        },
                        {
                            label: 'Quiz Attempts',
                            data: [],
                            borderColor: '#a855f7', // purple-500
                            backgroundColor: gradientPurple,
                            borderWidth: 2, 
                            fill: true, 
                            tension: 0.4, 
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#a855f7',
                            pointBorderWidth: 2,
                            pointRadius: 4, 
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { 
                        legend: { 
                            display: true, 
                            position: 'top', 
                            align: 'end',
                            labels: { 
                                usePointStyle: true, 
                                boxWidth: 8, 
                                padding: 20,
                                color: '#4b5563' // gray-600
                            }
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#111827', // gray-900
                            bodyColor: '#4b5563', // gray-600
                            borderColor: '#e5e7eb', // gray-200
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            titleFont: { size: 13, weight: 'bold' }
                        }
                    },
                    scales: {
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 12 } }
                        },
                        y: { 
                            beginAtZero: true, 
                            suggestedMax: 10, 
                            grid: { color: '#f3f4f6' }, // gray-100 
                            border: { display: false },
                            ticks: { font: { size: 12 }, padding: 10 }
                        }
                    }
                }
            });

            // Listen for Livewire updates dynamically
            window.addEventListener('update-chart-data', event => {
                const newData = event.detail[0]?.data || event.detail.data || event.detail;
                
                if(newData) {
                    accessChart.data.labels = newData.labels;
                    accessChart.data.datasets[0].data = newData.posts;
                    accessChart.data.datasets[1].data = newData.quizzes;
                    accessChart.update();
                }
            });
        });
    </script>
@endpush