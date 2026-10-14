<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\ParticipationService;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $groups = [];
    public $selectedGroupId = null;

    public string $search = '';
    public bool $showEnrollModal = false;

    // Form fields for new enrollment
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public int $pointsPerPost = 1;
    public int $pointsPerQuizCompletion = 5;

    public function mount()
    {
        $this->groups = Group::all();

        if ($this->groups->isNotEmpty()) {
            $this->selectedGroupId = $this->groups->first()->id;
        }
    }

    // Reset pagination when searching or changing groups
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedGroupId()
    {
        $this->resetPage();
    }

    // Dynamic data fetching with pagination scoped strictly to the selected group
    public function with(ParticipationService $participationService): array
    {
        $students = collect();
        $participation = [];

        if ($this->selectedGroupId) {
            $group = Group::find($this->selectedGroupId);

            if ($group) {
                $query = $group->members();

                if (!empty($this->search)) {
                    $query->where(function($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                }

                $students = $query->latest()->paginate(10);

                $participation = $participationService->computeForGroup($group);
                $criteria = $participationService->getCriteria($group);
                $this->pointsPerPost = $criteria['points_per_post'];
                $this->pointsPerQuizCompletion = $criteria['points_per_quiz_completion'];
            }
        }

        return [
            'students'      => $students,
            'participation' => $participation,
        ];
    }

    public function removeStudent($userId)
    {
        if ($this->selectedGroupId) {
            DB::table('group_members')
                ->where('group_id', $this->selectedGroupId)
                ->where('user_id', $userId)
                ->delete();

            session()->flash('success', 'Student removed from this group successfully.');
        }
    }
        public function saveCriteria(ParticipationService $participationService)
    {
        $this->validate([
            'pointsPerPost'            => 'required|integer|min:0|max:100',
            'pointsPerQuizCompletion'  => 'required|integer|min:0|max:100',
        ]);

        if ($this->selectedGroupId) {
            $group = Group::find($this->selectedGroupId);
            $participationService->setCriteria($group, $this->pointsPerPost, $this->pointsPerQuizCompletion);
            session()->flash('success', 'Participation criteria updated for this group.');
        }
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 min-h-screen bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 transition-colors duration-200">
    
    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 px-4 py-3 rounded-lg text-sm font-medium flex items-center justify-between shadow-sm">
            <span>{{ session('success') }}</span>
            <button wire:click="$set('session', null)" class="text-emerald-600 hover:text-emerald-800 font-bold text-lg">&times;</button>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Students Directory</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Manage active student profiles scoped to specific study groups, evaluate standings, and track platform records.</p>
        </div>
    </div>

    <!-- Group Selector & Filter Controls Bar -->
    <div class="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 rounded-xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="w-full md:w-72">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-1">Active Study Group</label>
            <select wire:model.live="selectedGroupId" class="w-full bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name ?? $group->groupName }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full md:w-96 relative md:mt-5">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search members in this group..." class="w-full pl-10 pr-4 py-2 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
        </div>
    </div>

    <!-- Main Table Section (Scoped strictly to selected Group) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="overflow-x-auto">
            <div class="mb-4 p-4 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Points per post</label>
                    <input type="number" wire:model="pointsPerPost" min="0" max="100" class="w-24 px-3 py-1.5 text-sm border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-zinc-900">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Points per quiz completed</label>
                    <input type="number" wire:model="pointsPerQuizCompletion" min="0" max="100" class="w-24 px-3 py-1.5 text-sm border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-zinc-900">
                </div>
                <button wire:click="saveCriteria" class="px-4 py-2 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                    Save Criteria
                </button>
                @if (session('success'))
                    <span class="text-xs text-emerald-600 font-semibold">{{ session('success') }}</span>
                @endif
            </div>
            <table class="w-full text-left text-sm border-collapse min-w-[600px]">
                <thead class="bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-xs">
                    <tr>
                        <th class="p-4">Student Details</th>
                        <th class="p-4">Email Address</th>
                        <th class="p-4">Participation</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 font-medium text-zinc-700 dark:text-zinc-300">
                    @forelse($students as $student)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-950/50 transition-colors" wire:key="student-{{ $student->id }}">
                            
                            <td class="p-4">
                                <span class="block font-bold text-zinc-900 dark:text-white">{{ $student->name }}</span>
                            </td>
                            
                            <td class="p-4 text-zinc-500 dark:text-zinc-400">
                                {{ $student->email }}
                            </td>

                            <td class="p-4 text-zinc-500 dark:text-zinc-400">
                                @php($p = $participation[$student->id] ?? ['posts_count' => 0, 'quizzes_completed' => 0, 'participation_score' => 0])
                                <span class="block font-bold text-zinc-900 dark:text-white">{{ $p['participation_score'] }} pts</span>
                                <span class="block text-xs">{{ $p['posts_count'] }} posts &middot; {{ $p['quizzes_completed'] }} quizzes</span>
                            </td>
                            
                            <td class="p-4 text-right">
                                <button wire:click="removeStudent({{ $student->id }})" wire:confirm="Remove this student from the current group?" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-semibold transition-colors">
                                    Remove from Group
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-8 h-8 mb-2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <p>No members registered in this specific group yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Links -->
        @if($students->isNotEmpty() && method_exists($students, 'hasPages') && $students->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950">
                {{ $students->links() }}
            </div>
        @endif
    </div>

    <!-- Enroll Student to Group Modal -->
    @if($showEnrollModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Enroll Student to Group</h3>
                    <button wire:click="$set('showEnrollModal', false)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="enrollStudent" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Full Name</label>
                        <input type="text" wire:model="name" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        @error('name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Email Address</label>
                        <input type="email" wire:model="email" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        @error('email') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Temporary Password</label>
                        <input type="password" wire:model="password" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        @error('password') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Initial Standing</label>
                        <select wire:model="standing" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Satisfactory">Satisfactory</option>
                            <option value="At Risk">At Risk</option>
                        </select>
                        @error('standing') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" wire:click="$set('showEnrollModal', false)" class="px-4 py-2 text-sm font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-colors">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-sm transition-colors">Enroll Student</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>