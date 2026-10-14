<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Group;
use App\Models\Topic;
use App\Models\Blacklist;
use App\Services\TopicService;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public $groupList = [];
    public $topics = [];
    public $pendingFlags = [];
    public int $flagCount = 0;
    public ?int $selectedGroupId = null;

    // Create Topic form state (real Topic columns — title, description,
    // classification, is_private — no invented fields).
    public string $newTopicTitle = '';
    public string $newTopicDescription = '';
    public string $newTopicClassification = '';
    public bool $newTopicPrivate = false;

    // Manage Topic modal state
    public bool $showManageModal = false;
    public ?array $activeManageTopic = null;

    public function mount(): void
    {
        $this->refreshGroupList();
        if ($this->groupList->isNotEmpty()) {
            $this->selectedGroupId = $this->groupList->first()->id;
        }

        $this->loadTopics();
        $this->loadFlags();
    }

    private function refreshGroupList(): void
    {
        $this->groupList = Group::with('topics')->get();
    }

    public function loadTopics(): void
    {
        $query = Topic::query();
        if ($this->selectedGroupId) {
            $query->where('group_id', $this->selectedGroupId);
        }

        $this->topics = $query->withCount('posts')->latest()->get()->map(function ($topic) {
            return [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'classification' => $topic->classification,
                'is_private' => (bool) $topic->is_private,
                'replies' => $topic->posts_count ?? 0,
                'time' => $topic->created_at ? $topic->created_at->diffForHumans() : 'Just now',
            ];
        })->toArray();
    }

    public function loadFlags(): void
    {
        // Same definition of "flagged" as Admin > Members: an active,
        // unexpired suspension. Both sides now read this off the same
        // User::issueWarning()/manualSuspend()/pardon() writes, so this
        // list can no longer drift from what Admin shows.
        $flags = Blacklist::where('status', 'SUSPENDED')
            ->where('expiry_date', '>', now())
            ->with('user')
            ->get();

        $this->pendingFlags = $flags->map(function ($f) {
            return [
                'id' => $f->id,
                'user_id' => $f->user_id,
                'user' => $f->user->name ?? ('User #' . $f->user_id),
                'reason' => 'Warning count: ' . $f->warning_count
                    . ($f->expiry_date ? ' • Suspended until ' . $f->expiry_date->toFormattedDateString() : ''),
                'preview' => 'Flagged by the automated inactivity/warning engine.',
            ];
        })->toArray();

        $this->flagCount = count($this->pendingFlags);
    }

    public function updatedSelectedGroupId(): void
    {
        $this->loadTopics();
    }

    public function createTopic(TopicService $topicService): void
    {
        $this->validate([
            'newTopicTitle' => 'required|string|max:255',
            'newTopicDescription' => 'required|string|max:1000',
            'newTopicClassification' => 'nullable|string|max:50',
            'selectedGroupId' => 'required|exists:groups,id',
        ]);

        $topic = $topicService->createTopic([
            'title' => $this->newTopicTitle,
            'description' => $this->newTopicDescription,
            'group_id' => $this->selectedGroupId,
            'is_private' => $this->newTopicPrivate,
        ], Auth::user());

        if ($this->newTopicClassification !== '') {
            $topic->update(['classification' => $this->newTopicClassification]);
        }

        $this->reset(['newTopicTitle', 'newTopicDescription', 'newTopicClassification', 'newTopicPrivate']);

        $this->loadTopics();
        $this->refreshGroupList();
        $this->dispatch('topic-created');
        session()->flash('status', 'Discussion topic created successfully.');
    }

    public function openManageModal($topicId): void
    {
        $topic = Topic::find($topicId);
        if ($topic) {
            $this->activeManageTopic = [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'classification' => $topic->classification,
                'is_private' => (bool) $topic->is_private,
                'posts_count' => $topic->posts()->count(),
                'created_at' => $topic->created_at->toFormattedDateString(),
            ];
            $this->showManageModal = true;
        }
    }

    public function deleteTopic($topicId): void
    {
        Topic::where('id', $topicId)->delete();
        $this->showManageModal = false;
        $this->loadTopics();
        $this->refreshGroupList();
        session()->flash('status', 'Discussion topic deleted successfully.');
    }

    public function getPendingRequestsProperty()
    {
        return Topic::where('user_id', Auth::id())
            ->where('is_private', true)
            ->with(['participants' => fn ($q) => $q->wherePivot('status', 'pending')])
            ->get()
            ->flatMap(fn ($topic) => $topic->participants->map(fn ($u) => [
                'topic_id' => $topic->id, 'topic_title' => $topic->title,
                'user_id' => $u->id, 'user_name' => $u->name,
            ]));
    }

    public function approveRequest($topicId, $userId): void
    {
        app(TopicService::class)->approveParticipant($topicId, $userId, Auth::user());
        session()->flash('status', 'Participant approved.');
    }

    public function dismissFlag($flagId, ModerationService $moderationService): void
    {
        $entry = Blacklist::find($flagId);
        if ($entry) {
            $moderationService->reinstateUser($entry->user_id);
        }
        $this->loadFlags();
    }

    public function applyBan($flagId, ModerationService $moderationService): void
    {
        $entry = Blacklist::find($flagId);
        if ($entry) {
            $moderationService->suspendUser($entry->user_id);
        }
        $this->loadFlags();
    }
}; ?>

<div x-data="{ showCreateModal: false }" @topic-created.window="showCreateModal = false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 bg-white dark:bg-zinc-950 min-h-screen text-zinc-900 dark:text-zinc-100 transition-colors duration-200">

    <!-- Quick Links -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('lecturer.students') }}" class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-[#24a065] transition-all">
            <h3 class="text-xs font-black text-zinc-900 dark:text-white">Students Directory</h3>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Manage rosters and standings</p>
        </a>
        <a href="{{ route('lecturer.grades') }}" class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-[#24a065] transition-all">
            <h3 class="text-xs font-black text-zinc-900 dark:text-white">Grades Ledger</h3>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Review quiz reports</p>
        </a>
        <a href="{{ route('lecturer.quizzes') }}" class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-[#24a065] transition-all">
            <h3 class="text-xs font-black text-zinc-900 dark:text-white">Quiz Control</h3>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Build and publish assessments</p>
        </a>
        <a href="{{ route('reports') }}" class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-[#24a065] transition-all">
            <h3 class="text-xs font-black text-zinc-900 dark:text-white">Reports</h3>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Quiz performance reports</p>
        </a>
    </div>

    <!-- Your Groups -->
    <div class="space-y-3">
        <label class="text-[10px] font-black text-zinc-500 uppercase tracking-wider">Your Groups</label>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($groupList as $grp)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm space-y-1.5" wire:key="group-{{ $grp->id }}">
                    <h3 class="text-sm font-black text-zinc-900 dark:text-white">{{ $grp->groupName ?? $grp->name }}</h3>
                    @forelse($grp->topics as $t)
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 truncate">&bull; {{ $t->title }}</p>
                    @empty
                        <p class="text-[11px] text-zinc-400 italic">No topics yet</p>
                    @endforelse
                </div>
            @empty
                <div class="col-span-3 text-center py-8 text-xs text-zinc-400 italic">You aren't leading any groups yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 rounded-2xl shadow-sm border-t-4 border-t-[#24a065]">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white">Discussion Topics & Moderation</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Manage topics for a group and review flagged accounts.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 px-3 py-1.5 rounded-xl text-center">
                    <span class="block text-sm font-black text-[#24a065] dark:text-[#52c48a]">{{ count($topics) }}</span>
                    <span class="block text-[9px] text-zinc-400 font-bold uppercase tracking-wider">Topics</span>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 px-3 py-1.5 rounded-xl text-center">
                    <span class="block text-sm font-black text-amber-600 dark:text-amber-400">{{ $flagCount }}</span>
                    <span class="block text-[9px] text-zinc-400 font-bold uppercase tracking-wider">Flags</span>
                </div>
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="selectedGroupId" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#24a065] focus:outline-none transition-all">
                    @foreach($groupList as $grp)
                        <option value="{{ $grp->id }}">{{ $grp->groupName ?? $grp->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 px-4 py-3 rounded-xl text-xs font-bold flex items-center justify-between shadow-sm">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="space-y-6">

        <!-- Discussion Topics -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 rounded-2xl shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white">Discussion Topics</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Topics for the selected group.</p>
                </div>
                <button type="button" @click="showCreateModal = true" class="bg-[#24a065] hover:bg-[#1c7d4e] text-white font-bold text-xs px-4 py-2 rounded-xl shadow transition-colors cursor-pointer border-none flex items-center justify-center gap-1.5">
                    <span>+ Create Topic</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[320px] overflow-y-auto pr-1">
                @forelse($topics as $topic)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-950 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between gap-4" wire:key="topic-{{ $topic['id'] }}">
                        <div class="truncate">
                            <div class="flex items-center gap-1.5 mb-0.5">
                                @if($topic['classification'])
                                    <span class="text-[9px] font-black text-[#24a065] dark:text-[#52c48a] uppercase tracking-wider">{{ $topic['classification'] }}</span>
                                @endif
                                @if($topic['is_private'])
                                    <span class="text-[9px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider">Private</span>
                                @endif
                            </div>
                            <h3 class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ $topic['title'] }}</h3>
                            <span class="text-[10px] text-zinc-400 font-medium mt-1 block">{{ $topic['replies'] }} replies • Last post {{ $topic['time'] }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" wire:click="openManageModal({{ $topic['id'] }})" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white text-[11px] font-bold px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs cursor-pointer transition-colors">Manage</button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 text-center py-8 text-xs text-zinc-400 italic">No discussion topics found for this group.</div>
                @endforelse
            </div>

            <!-- Pending Topic Access Requests -->
            <div class="bg-zinc-50 dark:bg-zinc-950 rounded-xl p-4 border border-zinc-200 dark:border-zinc-800 mt-4">
                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-wider block mb-2">Pending Access Requests</label>
                @forelse($this->pendingRequests as $request)
                    <div class="flex items-center justify-between py-2 border-b border-zinc-200 dark:border-zinc-800 last:border-0">
                        <span class="text-xs text-zinc-700 dark:text-zinc-300">
                            <strong>{{ $request['user_name'] }}</strong> requested access to
                            <strong>{{ $request['topic_title'] }}</strong>
                        </span>
                        <button type="button"
                                wire:click="approveRequest({{ $request['topic_id'] }}, {{ $request['user_id'] }})"
                                class="bg-[#24a065] hover:bg-[#1c8552] text-white font-bold text-[10px] tracking-wide px-3 py-1.5 rounded-lg shadow transition-all cursor-pointer border-none">
                            Approve
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 italic">No pending requests.</p>
                @endforelse
            </div>
</div>

    <!-- Create Topic Modal -->
    <div x-show="showCreateModal" class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50" style="display: none;">
        <div @click.outside="showCreateModal = false" class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-2xl p-6 shadow-2xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <h3 class="text-sm font-black uppercase text-zinc-900 dark:text-white">Create Discussion Topic</h3>
                <button @click="showCreateModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 font-bold text-base cursor-pointer">&times;</button>
            </div>

            <form wire:submit.prevent="createTopic" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase">Group</label>
                    <select wire:model="selectedGroupId" class="w-full bg-zinc-50 dark:bg-zinc-950 text-xs border border-zinc-300 dark:border-zinc-700 rounded-lg p-2.5 text-zinc-800 dark:text-zinc-200 focus:ring-2 focus:ring-[#24a065] focus:outline-none" required>
                        @foreach($groupList as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->groupName ?? $grp->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase">Topic Title</label>
                    <input type="text" wire:model="newTopicTitle" placeholder="e.g., Distributed Database Consensus" class="w-full bg-zinc-50 dark:bg-zinc-950 text-xs border border-zinc-300 dark:border-zinc-700 rounded-lg p-2.5 text-zinc-800 dark:text-zinc-200 focus:ring-2 focus:ring-[#24a065] focus:outline-none" required />
                    @error('newTopicTitle') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase">Description</label>
                    <textarea wire:model="newTopicDescription" rows="3" placeholder="What is this topic about?" class="w-full bg-zinc-50 dark:bg-zinc-950 text-xs border border-zinc-300 dark:border-zinc-700 rounded-lg p-2.5 text-zinc-800 dark:text-zinc-200 focus:ring-2 focus:ring-[#24a065] focus:outline-none" required></textarea>
                    @error('newTopicDescription') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase">Classification <span class="normal-case font-medium text-zinc-400">(optional)</span></label>
                    <input type="text" wire:model="newTopicClassification" placeholder="e.g., CS_4103" class="w-full bg-zinc-50 dark:bg-zinc-950 text-xs border border-zinc-300 dark:border-zinc-700 rounded-lg p-2.5 text-zinc-800 dark:text-zinc-200 uppercase focus:ring-2 focus:ring-[#24a065] focus:outline-none" />
                </div>

                <label class="flex items-center gap-2 text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    <input type="checkbox" wire:model="newTopicPrivate" class="rounded border-zinc-300 dark:border-zinc-700 text-[#24a065] focus:ring-[#24a065]" />
                    Private topic (requires approval to join)
                </label>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-bold rounded-xl cursor-pointer border-none transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-[#24a065] hover:bg-[#1c7d4e] text-white text-xs font-bold rounded-xl shadow cursor-pointer border-none transition-colors">Save & Publish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Topic Modal -->
    @if($showManageModal && $activeManageTopic)
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-2xl p-6 shadow-2xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <h3 class="text-sm font-black uppercase text-zinc-900 dark:text-white">Manage Topic</h3>
                <button wire:click="$set('showManageModal', false)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 font-bold text-base cursor-pointer">&times;</button>
            </div>

            <div class="space-y-3 text-xs text-zinc-700 dark:text-zinc-300">
                <p><strong>Title:</strong> {{ $activeManageTopic['title'] }}</p>
                <p><strong>Description:</strong> {{ $activeManageTopic['description'] }}</p>
                @if($activeManageTopic['classification'])
                    <p><strong>Classification:</strong> {{ $activeManageTopic['classification'] }}</p>
                @endif
                <p><strong>Visibility:</strong> {{ $activeManageTopic['is_private'] ? 'Private' : 'Public' }}</p>
                <p><strong>Total Replies:</strong> {{ $activeManageTopic['posts_count'] }}</p>
                <p><strong>Created:</strong> {{ $activeManageTopic['created_at'] }}</p>
            </div>

            <div class="flex justify-between items-center gap-2 mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <button type="button" wire:click="deleteTopic({{ $activeManageTopic['id'] }})" wire:confirm="Are you sure you want to delete this discussion topic?" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl cursor-pointer border-none transition-colors">Delete Topic</button>
                <button type="button" wire:click="$set('showManageModal', false)" class="px-5 py-2 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold rounded-xl cursor-pointer border-none transition-colors">Close</button>
            </div>
        </div>
    </div>
    @endif
</div>