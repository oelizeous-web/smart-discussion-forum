<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Group;
use App\Models\Post;
use App\Models\Mark;
use App\Services\ParticipationService;

new #[Layout('layouts.app')] class extends Component {
    public array $groupComparison = [];
    public array $weeklyActivity = [];
    public array $topline = [];

    public function mount(ParticipationService $participationService): void
    {
        $this->loadReport($participationService);
    }

    /**
     * Reports is the one page that looks ACROSS all groups at once —
     * Students and Grades already cover per-student and per-quiz detail
     * for a single group, so this stays deliberately at the group level
     * (req #7: "each group should get its own statistics").
     */
    public function loadReport(ParticipationService $participationService): void
    {
        $groups = Group::withCount(['members', 'topics'])->get();

        $this->groupComparison = $groups->map(function ($group) use ($participationService) {
            $participation = $participationService->computeForGroup($group);
            $totalScore = array_sum(array_column($participation, 'participation_score'));
            $activeMembers = collect($participation)->filter(fn ($p) => $p['participation_score'] > 0)->count();
            $memberCount = max($group->members_count, 1);

            $avgQuizScore = Mark::whereIn('quiz_id', $group->quizzes()->pluck('id'))->avg('score');

            return [
                'id' => $group->id,
                'name' => $group->groupName ?? $group->name,
                'members' => $group->members_count,
                'topics' => $group->topics_count,
                'avg_quiz_score' => $avgQuizScore !== null ? round($avgQuizScore, 1) : null,
                'participation_total' => $totalScore,
                'engagement_pct' => round(($activeMembers / $memberCount) * 100),
            ];
        })->sortByDesc('engagement_pct')->values()->toArray();

        // --- Simple 6-week posting trend, across all groups ---
        $weeks = collect(range(5, 0))->map(fn ($i) => now()->subWeeks($i)->startOfWeek());
        $counts = Post::where('created_at', '>=', now()->subWeeks(6)->startOfWeek())
            ->get()
            ->groupBy(fn ($post) => $post->created_at->startOfWeek()->toDateString());

        $maxCount = 1;
        $this->weeklyActivity = $weeks->map(function ($weekStart) use ($counts, &$maxCount) {
            $count = $counts->get($weekStart->toDateString(), collect())->count();
            $maxCount = max($maxCount, $count);
            return ['label' => $weekStart->format('M j'), 'count' => $count];
        })->toArray();

        foreach ($this->weeklyActivity as &$w) {
            $w['pct'] = round(($w['count'] / $maxCount) * 100);
        }

        // --- Topline totals ---
        $this->topline = [
            ['label' => 'Groups', 'value' => $groups->count()],
            ['label' => 'Total Members', 'value' => $groups->sum('members_count')],
            ['label' => 'Posts (Last 6 Weeks)', 'value' => array_sum(array_column($this->weeklyActivity, 'count'))],
            ['label' => 'Participation Points Awarded', 'value' => array_sum(array_column($this->groupComparison, 'participation_total'))],
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 bg-white dark:bg-zinc-950 min-h-screen text-zinc-900 dark:text-zinc-100 transition-colors duration-200">

    <!-- Topline -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($topline as $card)
            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl flex flex-col gap-1 shadow-xs">
                <span class="text-[10px] text-zinc-400 font-extrabold uppercase tracking-wider">{{ $card['label'] }}</span>
                <span class="text-xl font-black text-[#24a065] dark:text-[#52c48a]">{{ $card['value'] }}</span>
            </div>
        @endforeach
    </div>

        <!-- Group Comparison -->
        <div class="xl:col-span-8 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800">
                <h3 class="font-black text-xs uppercase tracking-wider text-zinc-700 dark:text-zinc-400">Groups at a Glance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-zinc-50/50 dark:bg-zinc-950/20 border-b border-zinc-200 dark:border-zinc-800 font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        <tr>
                            <th class="p-3.5">Group</th>
                            <th class="p-3.5 text-center">Members</th>
                            <th class="p-3.5 text-center">Topics</th>
                            <th class="p-3.5 text-center">Avg Quiz Score</th>
                            <th class="p-3.5 text-center">Participation Points</th>
                            <th class="p-3.5 text-left w-40">Engagement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 font-medium text-zinc-700 dark:text-zinc-300">
                        @forelse($groupComparison as $g)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-950/20 transition-colors" wire:key="group-{{ $g['id'] }}">
                                <td class="p-3.5 font-bold text-zinc-900 dark:text-white">{{ $g['name'] }}</td>
                                <td class="p-3.5 text-center">{{ $g['members'] }}</td>
                                <td class="p-3.5 text-center">{{ $g['topics'] }}</td>
                                <td class="p-3.5 text-center font-mono">{{ $g['avg_quiz_score'] !== null ? $g['avg_quiz_score'] . '%' : '—' }}</td>
                                <td class="p-3.5 text-center font-mono font-black text-zinc-900 dark:text-white">{{ $g['participation_total'] }}</td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#24a065] rounded-full" style="width: {{ $g['engagement_pct'] }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 w-8 text-right">{{ $g['engagement_pct'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-zinc-400 italic">No groups found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Weekly Activity Trend -->
        <div class="xl:col-span-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm flex flex-col gap-4">
            <div>
                <h3 class="font-black text-xs uppercase tracking-wider text-zinc-700 dark:text-zinc-400">Posting Activity</h3>
                <p class="text-[10px] text-zinc-400 font-semibold mt-0.5">Total posts per week, all groups combined.</p>
            </div>

            <div class="flex flex-col gap-2.5">
                @foreach($weeklyActivity as $week)
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold text-zinc-400 w-12 shrink-0">{{ $week['label'] }}</span>
                        <div class="flex-1 h-3 bg-zinc-50 dark:bg-zinc-950 rounded-full overflow-hidden border border-zinc-100 dark:border-zinc-800">
                            <div class="h-full bg-[#52c48a] rounded-full transition-all" style="width: {{ $week['pct'] }}%"></div>
                        </div>
                        <span class="text-[10px] font-mono font-bold text-zinc-700 dark:text-zinc-300 w-6 text-right">{{ $week['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

</div>