<?php
use Livewire\Volt\Component;

new class extends Component {
    public function markRead($id) {
        auth()->user()->notifications()->findOrFail($id)->markAsRead();
    }
}; ?>

<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" class="relative">
        🔔
        @if(auth()->user()->unreadNotifications->count())
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1">
                {{ auth()->user()->unreadNotifications->count() }}
            </span>
        @endif
    </button>
    <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-72 bg-white shadow-lg rounded-lg z-50">
        @forelse(auth()->user()->notifications()->latest()->limit(10)->get() as $n)
            <a href="{{ route('forums', ['topic' => $n->data['topic_id'] ?? '', 'post' => $n->data['post_id'] ?? '']) }}"
               wire:click="markRead('{{ $n->id }}')"
               class="block p-3 border-b text-sm {{ $n->read_at ? 'text-zinc-400' : 'font-semibold' }}">
                {{ $n->data['message'] }}
            </a>
        @empty
            <p class="p-3 text-sm text-zinc-400">No notifications yet.</p>
        @endforelse
    </div>
</div>