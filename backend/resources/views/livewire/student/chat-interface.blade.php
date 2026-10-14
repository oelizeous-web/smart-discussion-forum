<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On; 
use Livewire\WithFileUploads;
use App\Services\PostService;
use App\Services\TopicService;
use App\Services\GroupService;
use App\Services\ModerationService;
use App\Models\Group;
use App\Models\Topic;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use WithFileUploads;

    public $expandedGroup = null; 
    public $activeTopic = null; 
    public $activeGroup = null; 
    public $showTopicModal = false;
    public $selectedGroupId = null;
    public $highlightPostId = null;
    public $newTopicTitle = '';
    public $newTopicDescription = '';
    public $isPrivate = false; 

    public $newMessage = '';
    public $attachment = null;
    
    public bool $showLeftSidebar = true;
    public bool $showCreateModal = false;
    public bool $showJoinModal = false;

    // --- Moderation State ---
    public $showFlagModal = false;
    public $flagReason = '';
    public $flaggingPostId = null;

    public function openAddTopicModal($groupId)
    {
        $this->selectedGroupId = $groupId;
        $this->showTopicModal = true;
    }

    public function saveTopic(TopicService $topicService)
    {
        $validated = $this->validate([
            'newTopicTitle' => 'required|string|min:3|max:255',
            'newTopicDescription' => 'nullable|string|max:1000',
            'isPrivate' => 'boolean', 
        ]);

        $topicService->createTopic([
            'title' => $this->newTopicTitle,
            'description' => $this->newTopicDescription,
            'group_id' => $this->selectedGroupId,
            'is_private' => $this->isPrivate,
        ], auth()->user());

        $this->reset(['showTopicModal', 'newTopicTitle', 'newTopicDescription', 'isPrivate', 'selectedGroupId']);
    }

    public function requestAccess(TopicService $topicService, $topicId)
    {
        $topicService->requestAccess($topicId, auth()->user());
    }

    public function approveParticipant(TopicService $topicService, $topicId, $userId)
    {
        $topicService->approveParticipant($topicId, $userId, auth()->user());
    }

        public function sendMessage(PostService $postService, ModerationService $moderationService)
    {
        if (!$this->activeTopic) return;
        
        $this->validate([
            'newMessage' => 'required_without:attachment|string|max:2000',
            'attachment' => 'nullable|file|max:10240', // Max 10MB
        ]);

        $attachmentPath = null;
        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('attachments', 'public');
        }

        // Save Message
        $postService->createPost([
            'content' => $this->newMessage,
            'attachment_path' => $attachmentPath
        ], $this->activeTopic);

        $moderationService->clearWarningsIfCompliant(auth()->user());

        $this->newMessage = '';
        $this->attachment = null;
        $this->dispatch('message-sent'); 
    }
    // --- Flagging Methods ---
    public function openFlagModal($postId)
    {
        $this->flaggingPostId = $postId;
        $this->showFlagModal = true;
    }

    public function submitFlag()
    {
        $this->validate(['flagReason' => 'required|string|max:255']);

        Report::create([
            'post_id' => $this->flaggingPostId,
            'reported_by' => auth()->id(),
            'reason' => $this->flagReason,
        ]);

        $this->reset(['showFlagModal', 'flagReason', 'flaggingPostId']);
        $this->dispatch('post-flagged'); 
    }

    public function warnParticipant(ModerationService $moderationService, $topicId, $userId)
    {
        $moderationService->warnParticipant($topicId, $userId, auth()->user());
    }

    /**
     * Export every message in the active topic to a downloadable PDF.
     * Re-checks authorization server-side so a locked-out user can't
     * export a private topic just by knowing the topic id.
     */
    public function exportTopicToPdf(PostService $postService)
    {
        if (!$this->activeTopic) {
            return;
        }

        $topic = Topic::with('group')->findOrFail($this->activeTopic);

        $participant = $topic->participants()->where('user_id', auth()->id())->first();
        $isAuthorized = !$topic->is_private
            || ($participant && $participant->pivot->status === 'approved')
            || $topic->user_id === auth()->id()
            || auth()->user()->hasRole('admin');

        abort_unless($isAuthorized, 403, 'You do not have access to export this topic.');

        $posts = $postService->getPostsByTopic($this->activeTopic);

        $messages = $posts->map(function ($post) {
            return [
                'sender' => $post->author->name ?? 'Unknown User',
                'text'   => $post->content,
                'time'   => $post->created_at->format('M d, Y g:i A'),
            ];
        });

        $pdf = Pdf::loadView('pdfs.topic-export', [
            'topic'      => $topic,
            'group'      => $topic->group,
            'messages'   => $messages,
            'exportedBy' => auth()->user()->name,
            'exportedAt' => now()->format('M d, Y g:i A'),
        ])->setPaper('a4');

        $filename = 'topic-' . Str::slug($topic->title) . '-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function getListeners()
    {
        if (!$this->activeTopic) return [];
        return [
            "echo-private:topic.{$this->activeTopic},MessageSent" => 'render'
        ];
    }
    
    public function refreshMessages() {}

    public function with(PostService $postService, GroupService $groupService): array
    {
        $formattedMessages = collect();
        $topicModel = null;
        $topicMembers = collect();
        $pendingRequests = collect();
        
        if ($this->activeTopic) {
            $topicModel = Topic::find($this->activeTopic);
            $posts = $postService->getPostsByTopic($this->activeTopic);
            
            $formattedMessages = $posts->map(function($post) {
                return [
                    'id'      => $post->id,
                    'sender'  => $post->author->name ?? 'Unknown User', 
                    'text'    => $post->content,
                    'time'    => $post->created_at->format('g:i A'),
                    'is_mine' => $post->user_id === auth()->id(),
                ];
            });

            if ($topicModel->is_private) {
                $topicMembers = $topicModel->participants()->wherePivot('status', 'approved')->get();
                
                if ($topicModel->user_id === auth()->id() || auth()->user()->hasRole('admin')) {
                    $pendingRequests = $topicModel->participants()->wherePivot('status', 'pending')->get();
                }
            } else {
                $topicMembers = $this->activeGroup ? $this->activeGroup->members()->get() : collect();
            }
        }

        return [
            'messages'        => $formattedMessages,
            'topicModel'      => $topicModel, 
            'groups'          => $groupService->getUserGroups(auth()->user()),
            'availableGroups' => $groupService->getAvailableGroups(auth()->user()),
            'members' => $topicMembers,
            'pendingRequests' => $pendingRequests,
        ];
    }

    public function toggleGroup($groupId)
    {
        $this->expandedGroup = $this->expandedGroup === $groupId ? null : $groupId;
    }

    public function joinGroup(GroupService $groupService, $groupId)
    {
        $groupService->joinGroup($groupId, auth()->user());
        $this->showJoinModal = false;
        $this->dispatch('group-joined'); 
    }

    public function selectTopic($topicId)
    {
        $this->activeTopic = $topicId;
        $topic = Topic::find($topicId);
        $this->activeGroup = $topic ? $topic->group : null;
    }

        public function mount()
    {
        if (request()->query('topic')) {
            $this->selectTopic((int) request()->query('topic'));
        }
        $this->highlightPostId = request()->query('post') ? (int) request()->query('post') : null;
    }

    #[On('group-created')]
    public function updateSidebar() {}
}; ?>

<div class="h-full w-full bg-[#F7F5EE] flex overflow-hidden" x-data="{ mobileNavOpen: false }">

    <!-- Mobile backdrop for groups/topics panel -->
    <div x-show="mobileNavOpen" x-cloak @click="mobileNavOpen = false"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

    <!-- LEFT PANEL: Groups & Topics -->
    <div :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full'"
         class="fixed inset-y-0 left-0 z-50 w-80 transform transition-transform duration-200 ease-out lg:static lg:translate-x-0 lg:shadow-none lg:z-auto bg-white border-r border-zinc-200 flex flex-col flex-shrink-0 lg:transition-all lg:duration-300 {{ $showLeftSidebar ? 'lg:w-80' : 'lg:w-16' }}">
        
        <div class="h-16 p-4 border-b border-zinc-200 bg-white flex items-center {{ $showLeftSidebar ? 'justify-between' : 'justify-center' }}">
            @if($showLeftSidebar)
                <h2 class="text-lg font-bold text-zinc-900 truncate">Your Groups</h2>
                <div class="flex items-center gap-1">
                    <x-modal-trigger>
                        <button class="p-1.5 bg-green-50 text-[#2F7A54] hover:bg-[#2F7A54] hover:text-white rounded-md transition" title="Create or Join Group">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </button>
                    </x-modal-trigger>
                    <button wire:click="$toggle('showLeftSidebar')" class="hidden lg:inline-flex text-zinc-400 hover:text-zinc-900 p-1.5 rounded-md hover:bg-zinc-100 transition" title="Toggle Sidebar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
                    </button>
                    <!-- Mobile close -->
                    <button @click="mobileNavOpen = false" class="lg:hidden text-zinc-400 hover:text-zinc-900 p-1.5 rounded-md hover:bg-zinc-100 transition" title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            @else
                <button wire:click="$toggle('showLeftSidebar')" class="text-zinc-400 hover:text-zinc-900 p-1 rounded-md hover:bg-zinc-100 transition" title="Expand Sidebar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                </button>
            @endif
        </div>
        
        <div class="flex-1 overflow-y-auto">
            @if($showLeftSidebar)
                
                <div class="px-2 space-y-1 pb-4">
                    @forelse($groups as $group)
                        <div>
                            <button wire:click="toggleGroup({{ $group->id }})" class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-zinc-100 transition">
                                <span class="font-semibold text-zinc-700">{{ $group->name }}</span>
                                <svg class="w-4 h-4 text-zinc-400 transition-transform {{ $expandedGroup === $group->id ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            @if($expandedGroup === $group->id)
                                <div class="mt-1 mb-2 ml-4 space-y-1 border-l-2 border-zinc-200 pl-2">
                                    @foreach($group->topics as $topic) 
                                        <button wire:click="selectTopic({{ $topic->id }})" class="w-full flex items-center justify-between p-2 rounded-lg text-sm transition {{ $activeTopic == $topic->id ? 'bg-[#2F7A54] text-white shadow-md' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900' }}">
                                            <span class="truncate pr-2">
                                                @if($topic->is_private) 🔒 @endif # {{ $topic->title }}
                                            </span>
                                        </button>
                                    @endforeach
                                    
                                    <button wire:click="openAddTopicModal({{ $group->id }})" class="w-full flex items-center gap-2 p-2 rounded-lg text-sm text-zinc-400 hover:text-[#2F7A54] hover:bg-zinc-100 transition mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Add Topic
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <p class="text-sm text-zinc-400">You haven't joined any groups yet.</p>
                        </div>
                    @endforelse
                </div>
            @else
                <div class="flex flex-col items-center py-4 space-y-4">
                    @foreach($groups as $group)
                        <button wire:click="$set('showLeftSidebar', true); toggleGroup({{ $group->id }})" class="w-10 h-10 rounded-xl bg-zinc-100 hover:bg-[#2F7A54] flex items-center justify-center text-sm font-bold text-zinc-700 hover:text-white transition shadow-sm group relative" title="{{ $group->name }}">
                            {{ substr($group->name, 0, 1) }}
                        </button>
                    @endforeach
                    <button wire:click="$set('showLeftSidebar', true)" class="w-10 h-10 rounded-xl border border-dashed border-zinc-300 text-zinc-400 hover:border-[#2F7A54] hover:text-[#2F7A54] flex items-center justify-center transition" title="Add Group">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- CENTER PANEL -->
    <div class="flex-1 flex flex-col bg-[#F7F5EE] relative min-w-0" x-data="{ showMembers: false }">

        <!-- Mobile top bar -->
        <div class="h-14 flex items-center gap-3 px-4 border-b border-zinc-200 bg-white lg:hidden shrink-0">
            <button @click="mobileNavOpen = true" type="button" class="p-2 -ml-2 rounded-md text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="text-zinc-900 font-semibold text-sm truncate">{{ $topicModel->title ?? 'Discussions' }}</span>
        </div>
        
        @if($activeTopic && $topicModel)
            @php
                $participant = $topicModel->participants()->where('user_id', auth()->id())->first();
                $isAuthorized = !$topicModel->is_private || ($participant && $participant->pivot->status === 'approved');
            @endphp

            @if($isAuthorized)
                <!-- ACTIVE CHAT INTERFACE -->
                <div class="h-16 border-b border-zinc-200 bg-white flex items-center px-4 sm:px-6 flex-shrink-0 justify-between">
                    <div>
                        <h3 class="text-zinc-900 font-bold text-lg leading-tight flex items-center gap-2">
                            <span class="text-zinc-400">@if($topicModel->is_private) 🔒 @else # @endif</span> {{ $topicModel->title }}
                        </h3>
                        <p class="text-xs text-zinc-500">{{ $activeGroup ? $activeGroup->name : 'Group Name' }}</p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <!-- EXPORT TO PDF -->
                        <button wire:click="exportTopicToPdf"
                                wire:loading.attr="disabled"
                                wire:target="exportTopicToPdf"
                                class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900 transition px-3 py-1.5 rounded-md hover:bg-zinc-100 shadow-sm border border-transparent hover:border-zinc-200 disabled:opacity-50"
                                title="Export chat to PDF">
                            <svg wire:loading.remove wire:target="exportTopicToPdf" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v10a2 2 0 01-2 2z"></path></svg>
                            <svg wire:loading wire:target="exportTopicToPdf" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-sm font-medium hidden sm:block">Export</span>
                        </button>

                        <button @click="showMembers = true" class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900 transition px-3 py-1.5 rounded-md hover:bg-zinc-100 shadow-sm border border-transparent hover:border-zinc-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span class="text-sm font-medium hidden sm:block">Members</span>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div wire:poll.2s.visible="refreshMessages" 
                     x-data="{ scroll() { $el.scrollTop = $el.scrollHeight; } }" 
                     x-init="scroll()" 
                     x-on:message-sent.window="setTimeout(scroll, 100)" 
                     class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-zinc-300 hover:[&::-webkit-scrollbar-thumb]:bg-zinc-400 [&::-webkit-scrollbar-thumb]:rounded-full">
                    
                    <!-- WARNING BANNER -->
                        @php
                            $blacklistWarnings = auth()->user()->blacklist->warning_count ?? 0;
                            $platformWarnings = auth()->user()->warning_count;
                        @endphp
                        @if($platformWarnings > 0 || $blacklistWarnings > 0)
                        <div class="bg-orange-50 border-l-4 border-orange-400 p-4 mb-6 rounded-r-lg shadow-sm">
                            <div class="flex items-center">
                                <svg class="h-6 w-6 text-orange-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <h3 class="text-orange-700 font-bold text-sm">Account Warning ({{ auth()->user()->warning_count }}/3 Strikes)</h3>
                                    <p class="text-orange-700/80 text-sm mt-1">
                                        You have received warnings for platform inactivity or inappropriate behavior. Reach 3 strikes, and your account will be suspended.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @forelse($messages as $message)
                        <div wire:key="msg-{{ $message['id'] }}" 
                             x-data="{
                                 shareText: '{{ addslashes($message['text']) }}',
                                 shareUrl: window.location.href,
                                 triggerShare() {
                                     if (navigator.share) {
                                         navigator.share({
                                             title: 'Forwarded Message',
                                             text: this.shareText,
                                             url: this.shareUrl
                                         }).catch(console.error);
                                     } else {
                                         window.open(
                                             'https://twitter.com/intent/tweet?text=' + encodeURIComponent('Check out this post: ' + this.shareText) + '&url=' + encodeURIComponent(this.shareUrl),
                                             '_blank',
                                             'width=600,height=400,scrollbars=yes'
                                         );
                                     }
                                 }
                             }"
                             class="group flex {{ $message['is_mine'] ? 'justify-end' : 'justify-start' }}">
                            
                            <div class="max-w-[90%] lg:max-w-[75%] flex flex-col {{ $message['is_mine'] ? 'items-end' : 'items-start' }}">
                                
                                @if(!$message['is_mine'])
                                    <span class="text-xs text-zinc-500 ml-1 mb-1 block">{{ $message['sender'] }}</span>
                                @else
                                    <span class="text-xs text-zinc-500 mr-1 mb-1 block text-right">You</span>
                                @endif

                                <div class="flex items-center gap-2 {{ $message['is_mine'] ? 'flex-row-reverse' : 'flex-row' }}">
                                    <div class="p-3 rounded-2xl shadow-sm {{ $message['is_mine'] ? 'bg-[#2F7A54] text-white rounded-tr-none' : 'bg-white text-zinc-800 border border-zinc-200 rounded-tl-none' }}">
                                        @if(!empty($message['attachment']))
                                            <div class="mb-2">
                                                <a href="{{ asset('storage/' . $message['attachment']) }}" target="_blank" class="flex items-center gap-2 text-sm bg-black/10 p-2 rounded-lg hover:bg-black/20 transition w-fit">
                                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                    <span class="underline">View Attachment</span>
                                                </a>
                                            </div>
                                        @endif
                                        {{ $message['text'] }}
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <button type="button" 
                                                @click="triggerShare()"
                                                title="Forward Message"
                                                class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 p-1.5 rounded-full hover:bg-zinc-100 text-zinc-400 hover:text-blue-500 focus:outline-none focus:opacity-100">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316M15 12a3 3 0 100-6 3 3 0 000 6zm0 0a3 3 0 100 6 3 3 0 000-6z"></path>
                                            </svg>
                                        </button>
                                        
                                        <!-- NEW: FLAG BUTTON -->
                                        @if(!$message['is_mine'])
                                            <button type="button" 
                                                    wire:click="openFlagModal({{ $message['id'] }})"
                                                    title="Flag Message"
                                                    class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 p-1.5 rounded-full hover:bg-zinc-100 text-zinc-400 hover:text-red-500 focus:outline-none focus:opacity-100">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                
                                <span class="text-[10px] text-zinc-400 mt-1 block {{ $message['is_mine'] ? 'mr-1' : 'ml-1' }}">{{ $message['time'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-full text-zinc-400">
                            <svg class="w-12 h-12 mb-4 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    @endforelse
                </div>

                <!-- MESSAGE INPUT AREA WITH ATTACHMENT -->
                <div class="p-4 bg-white border-t border-zinc-200 shrink-0">
                    
                    @if($attachment)
                        <div class="mb-3 flex items-center gap-2 text-sm text-blue-600 bg-blue-50 p-2.5 rounded-lg border border-blue-200 w-fit max-w-full">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            <span class="truncate">{{ $attachment->getClientOriginalName() }}</span>
                            <button type="button" wire:click="$set('attachment', null)" class="text-zinc-400 hover:text-red-500 ml-2 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    @endif

                    <form wire:submit="sendMessage" class="flex items-center gap-3 max-w-4xl mx-auto" wire:key="chat-input-form">
                        
                        <input type="file" id="file-upload" wire:model="attachment" class="hidden">
                        <label for="file-upload" class="cursor-pointer text-zinc-400 hover:text-[#2F7A54] p-2 rounded-full hover:bg-zinc-100 transition" title="Attach File">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        </label>

                        <div wire:loading wire:target="attachment" class="text-[#2F7A54]">
                            <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </div>

                        <input 
                            type="text" 
                            wire:model="newMessage" 
                            placeholder="Type a message..." 
                            class="flex-1 bg-zinc-50 border border-zinc-300 text-zinc-900 rounded-full px-5 py-3 focus:outline-none focus:border-[#2F7A54] shadow-inner placeholder-zinc-400 transition-colors"
                            autocomplete="off"
                            wire:key="chat-text-input" 
                            wire:loading.attr="disabled"
                            wire:target="attachment"
                        >

                        <button 
                            type="submit" 
                            class="bg-[#2F7A54] hover:bg-[#256242] text-white p-3 rounded-full transition shadow-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center h-12 w-12 shrink-0"
                            wire:loading.attr="disabled" wire:target="sendMessage">
                            <svg wire:loading.remove wire:target="sendMessage" class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            <svg wire:loading wire:target="sendMessage" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </button>
                    </form>
                    @error('newMessage') <span class="text-red-500 text-xs ml-14 mt-1 block">{{ $message }}</span> @enderror
                    @error('attachment') <span class="text-red-500 text-xs ml-14 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- SLIDE-OVER DRAWER: Active Members & Pending Requests -->
                <div x-show="showMembers" x-transition.opacity @click="showMembers = false" class="absolute inset-0 bg-black/40 z-40" style="display: none;"></div>
                <div x-show="showMembers" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="absolute inset-y-0 right-0 w-full max-w-xs sm:w-72 sm:max-w-none bg-white border-l border-zinc-200 shadow-2xl flex flex-col z-50" style="display: none;">
                    <div class="h-16 p-4 border-b border-zinc-200 flex items-center justify-between bg-white/95 backdrop-blur">
                        <h2 class="text-sm font-bold text-zinc-700 uppercase tracking-wider">Group Members</h2>
                        <button @click="showMembers = false" class="text-zinc-400 hover:text-zinc-900 p-1 rounded-md hover:bg-zinc-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="p-4 overflow-y-auto flex-1 space-y-6">
                        @if($pendingRequests && $pendingRequests->count() > 0)
                            <div>
                                <h3 class="text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-3 border-b border-zinc-200 pb-2">
                                    Pending Approvals ({{ $pendingRequests->count() }})
                                </h3>
                                <div class="space-y-2">
                                    @foreach($pendingRequests as $applicant)
                                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 border border-zinc-200 shadow-sm">
                                            <div class="flex items-center gap-2 overflow-hidden">
                                                <div class="shrink-0 h-8 w-8 rounded-full bg-zinc-200 flex items-center justify-center text-xs font-bold text-zinc-600">
                                                    {{ strtoupper(substr($applicant->name, 0, 1)) }}
                                                </div>
                                                <span class="text-sm font-medium text-zinc-800 truncate">{{ $applicant->name }}</span>
                                            </div>
                                            <button wire:click="approveParticipant({{ $topicModel->id }}, {{ $applicant->id }})" class="shrink-0 text-xs bg-[#2F7A54] hover:bg-[#256242] text-white px-3 py-1.5 rounded-md transition font-medium">
                                                Approve
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div>
                            <h3 class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-3 border-b border-zinc-200 pb-2">
                                @if($topicModel && $topicModel->is_private) Approved Participants @else Group Members @endif
                            </h3>
                            <div class="space-y-1">
                            @forelse($members as $member)
                                <div class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-zinc-100 cursor-pointer transition">
                                    <div class="shrink-0 h-10 w-10 rounded-full overflow-hidden bg-zinc-100 flex items-center justify-center border border-zinc-200 relative shadow-sm">
                                        @if($member->avatar)
                                            <img src="{{ asset('storage/' . $member->avatar) }}" alt="{{ $member->name }}" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-zinc-600 font-bold text-sm">{{ strtoupper(substr($member->name, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-zinc-800 truncate">{{ $member->name }}</p>
                                        @if($member->bio)
                                            <p class="text-[11px] text-zinc-500 truncate" title="{{ $member->bio }}">{{ $member->bio }}</p>
                                        @else
                                            <p class="text-[11px] text-zinc-400 truncate">{{ $member->getRoleNames()->first() ?? 'Member' }}</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                    <div class="flex flex-col items-center justify-center py-6 text-zinc-400">
                                        <p class="text-sm italic">No active members.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <!-- THE WAITING ROOM -->
                <div class="flex-1 flex flex-col items-center justify-center p-10 text-center bg-zinc-50">
                    <div class="text-6xl mb-4">🔒</div>
                    <h2 class="text-2xl font-bold text-zinc-900 mb-2">Private Topic</h2>
                    <p class="text-zinc-500 mb-6 max-w-md">This topic is private. You need approval from the author to view or send messages.</p>
                    
                    @if(!$participant)
                        <button wire:click="requestAccess({{ $activeTopic }})" class="bg-[#2F7A54] hover:bg-[#256242] px-6 py-3 rounded-lg text-white font-medium transition shadow-md">
                            Request Access
                        </button>
                    @else
                        <div class="bg-white border border-zinc-200 px-6 py-3 rounded-lg text-zinc-600 flex items-center gap-2 shadow-sm">
                            <svg class="animate-spin h-5 w-5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Access Request Pending...
                        </div>
                    @endif
                </div>
            @endif

        @else
            <!-- ZERO-DATA STATE -->
            <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-8 bg-zinc-50">
                <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center mb-6 shadow-md border border-zinc-200">
                    <svg class="w-10 h-10 text-[#2F7A54]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                </div>
                
                <h2 class="text-2xl sm:text-3xl font-bold text-zinc-900 mb-2 text-center">Welcome to Discussions</h2>
                <p class="text-zinc-500 max-w-md text-center mb-10">Select a topic from the sidebar to start chatting, or explore new groups to expand your network.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl">
                    <x-modal-trigger>
                        <button class="flex flex-col items-center p-6 w-full bg-white border border-zinc-200 rounded-2xl hover:border-[#2F7A54] hover:bg-zinc-50 transition group text-center cursor-pointer shadow-sm">
                            <div class="w-12 h-12 bg-green-50 text-[#2F7A54] rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></div>
                            <h3 class="text-zinc-900 font-bold mb-2">Create a Group</h3>
                            <p class="text-xs text-zinc-500">Start a new study circle, define your topics, and manage members.</p>
                        </button>
                    </x-modal-trigger>

                    <button type="button" wire:click="$dispatch('open-join-modal')" class="flex flex-col items-center p-6 bg-white border border-zinc-200 rounded-2xl hover:border-emerald-500 hover:bg-zinc-50 transition group text-center cursor-pointer shadow-sm">
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
                        <h3 class="text-zinc-900 font-bold mb-2">Join a Group</h3>
                        <p class="text-xs text-zinc-500">Browse existing active groups and join the ongoing academic discussions.</p>
                    </button>
                </div>
            </div>
        @endif
    </div>
    
    <!-- TOPIC CREATION MODAL -->
    @if($showTopicModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-xl w-full max-w-96 shadow-2xl border border-zinc-200">
            <h3 class="text-lg font-semibold text-zinc-900 mb-4">Create New Topic</h3>
            
            <div class="mb-4">
                <label class="block text-zinc-500 text-sm mb-2">Topic Title</label>
                <input type="text" wire:model="newTopicTitle" class="w-full p-3 bg-zinc-50 border border-zinc-300 text-zinc-900 rounded-lg focus:outline-none focus:border-[#2F7A54]" placeholder="e.g., Assignment 1 Discussion">
                @error('newTopicTitle') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-zinc-500 text-sm mb-2">Description</label>
                <textarea wire:model="newTopicDescription" rows="3" class="w-full p-3 bg-zinc-50 border border-zinc-300 text-zinc-900 rounded-lg focus:outline-none focus:border-[#2F7A54] resize-none" placeholder="Briefly describe what this topic is about..."></textarea>
                @error('newTopicDescription') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="mb-6 flex items-center">
                <input type="checkbox" wire:model="isPrivate" id="isPrivateCheckbox" class="w-4 h-4 text-[#2F7A54] bg-white border-zinc-300 rounded focus:ring-[#5CC98B] focus:ring-2 cursor-pointer">
                <label for="isPrivateCheckbox" class="ml-2 text-sm text-zinc-600 cursor-pointer">
                    Make this topic private (Requires approval to join)
                </label>
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <button wire:click="$set('showTopicModal', false)" class="px-4 py-2 text-zinc-500 hover:text-zinc-900 transition">Cancel</button>
                <button wire:click="saveTopic" class="px-4 py-2 bg-[#2F7A54] hover:bg-[#256242] text-white rounded-lg transition">Save Topic</button>
            </div>
        </div>
    </div>
    @endif

    <!-- FLAG REPORT MODAL -->
    <div x-data="{ show: $wire.entangle('showFlagModal') }" x-cloak>
        <div x-show="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
            <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                
                <div x-show="show" x-transition.opacity class="fixed inset-0 transition-opacity bg-black/50 backdrop-blur-sm" aria-hidden="true" @click="show = false"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl border border-zinc-200 relative z-10">
                    
                    <h2 class="text-xl font-bold mb-2 text-zinc-900 flex items-center" id="modal-title">
                        <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Report Message
                    </h2>
                    <p class="text-sm text-zinc-500 mb-5">Why are you flagging this message? The administrator will review this report and take appropriate action.</p>
                    
                    <form wire:submit.prevent="submitFlag">
                        <div class="mb-5">
                            <label class="block text-sm font-medium mb-2 text-zinc-600">Reason for reporting</label>
                            <textarea wire:model="flagReason" rows="3" class="w-full bg-zinc-50 border border-zinc-300 rounded-lg p-3 text-zinc-900 focus:ring-red-500 focus:border-red-500 transition-colors" placeholder="E.g., Off-topic, spam, inappropriate language..."></textarea>
                            @error('flagReason') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end space-x-3 mt-2">
                            <button type="button" @click="show = false" class="px-4 py-2 text-sm font-medium text-zinc-700 bg-zinc-100 rounded-lg hover:bg-zinc-200 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors shadow-md">
                                Submit Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <livewire:student.create-group-modal />
    <livewire:student.join-group-modal />
</div>