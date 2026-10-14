<?php
use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Services\GroupService;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public bool $showJoinModal = false;

    #[On('open-join-modal')]
    public function showModal()
    {
        $this->showJoinModal = true;
    }

    public function joinGroup(GroupService $groupService, $groupId)
    {
        // 1. Tell the service to do the database work
        $groupService->joinGroup($groupId, Auth::user());
        
        // 2. Update the UI state
        $this->showJoinModal = false; // Fixed typo here
        $this->dispatch('group-joined'); 
    }

    // Inject the service here to get the data for the view
    public function with(GroupService $groupService): array
    {
        return [
            'availableGroups' => $groupService->getAvailableGroups(Auth::user()),
        ];
    }
}; 
?>

<div>
    @if($showJoinModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 p-4">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl border border-stone-200">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Available Groups</h2>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($availableGroups as $group)
                        <div class="flex items-center justify-between p-3 bg-stone-50 border border-stone-200 rounded-lg">
                            <span class="text-slate-700">{{ $group->name }}</span>
                            <button wire:click="joinGroup({{ $group->id }})" class="px-3 py-1 bg-green-700 hover:bg-green-600 text-white text-sm rounded-md transition">
                                Join
                            </button>
                        </div>
                    @empty
                        <p class="text-slate-500 text-center">No new groups to join right now.</p>
                    @endforelse
                </div>

                <button wire:click="$set('showJoinModal', false)" class="mt-6 w-full py-2 text-slate-500 hover:text-slate-900 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif
</div>