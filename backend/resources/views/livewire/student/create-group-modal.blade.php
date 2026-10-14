<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;
use App\Services\GroupService;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public bool $showModal = false;

    #[Validate('required|string|max:255')]
    public $newGroupName = '';

    #[Validate('required|string|max:255')]
    public $newGroupTopic = ''; 

    #[Validate('required|string|max:255')]
    public $newTopicDescription = '';

    #[On('open-create-group-modal')] 
    public function openModal()
    {
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->reset(['newGroupName', 'newTopicDescription', 'newGroupTopic', 'showModal']);
        $this->resetValidation();
    }

    public function createGroup(GroupService $groupService)
    {
        $this->validate();

        try {
            // Pass the data to the Service
            $groupService->createGroupWithTopic([
                'name' => $this->newGroupName,
                'topic_title' => $this->newGroupTopic,
                'topic_description' => $this->newTopicDescription,
            ], Auth::user());

            $this->dispatch('group-created');
            $this->closeModal(); 
            $this->dispatch('notify', message: 'Group created successfully!');

        } catch (\Exception $e) {
            logger($e->getMessage()); 
            session()->flash('error', 'Failed to create group. Please try again.');
        }
    }
}; ?>

<div>
    @if($showModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity">
            <!-- FIXED: Added .stop to prevent click.away triggering when clicking the modal content itself -->
            <div class="bg-white border border-stone-200 rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden flex flex-col" @click.away.stop="$wire.closeModal()">
                
                <!-- Header -->
                <div class="px-6 py-4 border-b border-stone-200 flex justify-between items-center bg-stone-50">
                    <h3 class="text-lg font-bold text-slate-900">Create New Group</h3>
                    <button wire:click="closeModal" class="text-slate-400 hover:text-slate-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Form Body -->
                <form wire:submit.prevent="createGroup" class="flex-1 overflow-y-auto">
                    <div class="p-6 space-y-5">
                        
                        <!-- Session Error Alert -->
                        @if (session()->has('error'))
                            <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg">
                                {{ session('error') }}
                            </div>
                        @endif

                        <div>
                            <label for="newGroupName" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Group Name <span class="text-red-500">*</span></label>
                            <input type="text" id="newGroupName" wire:model="newGroupName" placeholder="e.g., Advanced Database Systems" class="w-full bg-white border border-stone-300 rounded-lg px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-green-600 focus:ring-1 focus:ring-green-600 transition">
                            @error('newGroupName') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="newGroupTopic" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Initial Topic <span class="text-red-500">*</span></label>
                            <p class="text-[11px] text-slate-500 mb-2">Every group needs at least one topic to start discussions.</p>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-400 font-bold">#</span>
                                <input type="text" id="newGroupTopic" wire:model="newGroupTopic" placeholder="e.g., Normalization Formats" class="w-full bg-white border border-stone-300 rounded-lg pl-8 pr-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-green-600 focus:ring-1 focus:ring-green-600 transition">
                            </div>
                            @error('newGroupTopic') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="newTopicDescription" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Description<span class="text-red-500">*</span></label>
                            <textarea id="newTopicDescription" wire:model="newTopicDescription" rows="2" placeholder="What is the topic about?" class="w-full bg-white border border-stone-300 rounded-lg px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-green-600 focus:ring-1 focus:ring-green-600 transition resize-none"></textarea>
                            <!-- FIXED: Added missing error handler -->
                            @error('newTopicDescription') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror 
                        </div>

                    </div>

                    <div class="px-6 py-4 border-t border-stone-200 bg-stone-50 flex justify-end gap-3">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-stone-100 rounded-lg transition">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium bg-green-700 hover:bg-green-600 text-white rounded-lg transition shadow-md">
                            <span wire:loading wire:target="createGroup">Saving...</span>
                            <span wire:loading.remove wire:target="createGroup">Create</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>