<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Report;
use App\Services\ModerationService;

new class extends Component {
    public $activeTab = 'members';

    public function with()
    {
        return [
            'users' => User::role('student')->paginate(15),
            'lecturers' => User::role('lecturer')->get(),
            'reports' => Report::where('is_resolved', false)->latest()->get(),
        ];
    }

    // --- Moderation Methods ---
    public function confirmFlag($reportId) {
        $report = Report::find($reportId);
        if ($report) {
            if ($report->post && $report->post->author) {
                $report->post->author->issueWarning();
                $report->post->delete();
            }
            $report->update(['is_resolved' => true]);
        }
    }

    public function dismissFlag($reportId) {
        $report = Report::find($reportId);
        if ($report) $report->update(['is_resolved' => true]);
    }

    public function warnUser($userId, ModerationService $moderationService)
    {
        $moderationService->warnUser($userId);
    }

    public function suspendUser($userId, ModerationService $moderationService)
    {
        $moderationService->suspendUser($userId);
    }

    public function reinstateUser($userId, ModerationService $moderationService)
    {
        $moderationService->reinstateUser($userId);
    }
    public function deleteUser($userId) {
        $user = User::find($userId);
        
        // Ensure the user exists and the admin isn't deleting themselves
        if ($user && $user->id !== auth()->id()) {
            $user->delete();
        }
    }
}; ?>

<div class="p-4 sm:p-6 lg:p-8 bg-gray-50 min-h-screen text-gray-900 font-sans" x-data="{ activeTab: @entangle('activeTab') }">
    
    <!-- Header Area -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">User Management</h1>
        <p class="text-sm text-gray-500 mt-1">Manage members, faculty, and community reports</p>
    </div>

    <!-- Tab Navigation -->
    <div class="flex overflow-x-auto border-b border-gray-200 mb-6 no-scrollbar">
        <button @click="activeTab = 'members'" 
                class="px-6 py-3 border-b-2 font-medium text-sm transition whitespace-nowrap"
                :class="activeTab === 'members' ? 'border-green-600 text-green-600' : 'text-gray-500 border-transparent hover:text-gray-800 hover:border-gray-300'">
            Members
        </button>
        <button @click="activeTab = 'lecturers'" 
                class="px-6 py-3 border-b-2 font-medium text-sm transition whitespace-nowrap"
                :class="activeTab === 'lecturers' ? 'border-green-600 text-green-600' : 'text-gray-500 border-transparent hover:text-gray-800 hover:border-gray-300'">
            Lecturers
        </button>
        <button @click="activeTab = 'moderation'" 
                class="px-6 py-3 border-b-2 font-medium text-sm transition whitespace-nowrap flex items-center gap-2"
                :class="activeTab === 'moderation' ? 'border-green-600 text-green-600' : 'text-gray-500 border-transparent hover:text-gray-800 hover:border-gray-300'">
            Moderation 
            @if($reports->count() > 0)
                <span class="bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs font-bold">{{ $reports->count() }}</span>
            @endif
        </button>
    </div>

    <!-- MEMBERS TAB -->
    <div x-show="activeTab === 'members'" x-cloak>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-semibold tracking-wider">
                        <tr>
                            <th class="p-4 sm:px-6">Name</th>
                            <th class="p-4 sm:px-6">Status</th>
                            <th class="p-4 sm:px-6">Warnings</th>
                            <th class="p-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50/80 transition duration-150" wire:key="user-{{ $user->id }}">
                                <td class="p-4 sm:px-6 font-medium text-gray-900 whitespace-nowrap">{{ $user->name }}</td>
                                <td class="p-4 sm:px-6 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-md {{ $user->status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $user->status }}
                                    </span>
                                </td>
                                <td class="p-4 sm:px-6 whitespace-nowrap">
                                    <span class="{{ $user->warning_count >= 2 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                        {{ $user->warning_count }} / 3
                                    </span>
                                </td>
                                <td class="p-4 sm:px-6 flex justify-end items-center space-x-4 whitespace-nowrap">
                                    @if($user->status !== 'SUSPENDED')
                                        <button wire:click="warnUser({{ $user->id }})" class="text-orange-600 hover:text-orange-700 text-sm font-medium transition">Warn</button>
                                        <button wire:click="suspendUser({{ $user->id }})" class="text-red-600 hover:text-red-700 text-sm font-medium transition">Suspend</button>
                                    @else
                                        <button wire:click="reinstateUser({{ $user->id }})" class="text-green-600 hover:text-green-700 text-sm font-bold transition">Pardon</button>
                                    @endif
                                    
                                    <!-- Delete Button (Trash Icon) -->
                                    <button wire:click="deleteUser({{ $user->id }})" 
                                            wire:confirm="Are you sure you want to permanently delete this user?" 
                                            class="text-gray-400 hover:text-red-600 transition" title="Delete User">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($users->hasPages())
                <div class="p-4 sm:px-6 border-t border-gray-100 bg-gray-50/50">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- LECTURERS TAB -->
    <div x-show="activeTab === 'lecturers'" x-cloak x-data="{ showModal: false }">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h2 class="text-lg font-bold text-gray-900">Faculty Members</h2>
            <button @click="showModal = true" class="bg-green-600 px-5 py-2.5 rounded-xl text-white text-sm font-medium hover:bg-green-700 transition shadow-sm focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                + Add Lecturer
            </button>
        </div>

        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4" x-cloak>
            <div @click.away="showModal = false" class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                <livewire:admin.register-lecturer-modal />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 divide-y divide-gray-100">
            @forelse($lecturers as $lecturer)
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 sm:px-6 hover:bg-gray-50/80 transition duration-150 gap-2" wire:key="lecturer-{{ $lecturer->id }}">
                    <span class="font-medium text-gray-900">{{ $lecturer->name }}</span>
                    <div class="flex items-center justify-between w-full sm:w-auto space-x-6">
                        <span class="text-gray-500 text-sm">{{ $lecturer->email }}</span>
                        <!-- Delete Button (Trash Icon) -->
                        <button wire:click="deleteUser({{ $lecturer->id }})" 
                                wire:confirm="Are you sure you want to remove this lecturer?" 
                                class="text-gray-400 hover:text-red-600 transition p-1 rounded-md hover:bg-red-50" title="Delete Lecturer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <p class="text-gray-500 text-sm">No lecturers registered.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- MODERATION TAB -->
    <div x-show="activeTab === 'moderation'" x-cloak>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-semibold tracking-wider">
                        <tr>
                            <th class="p-4 sm:px-6">Post Content</th>
                            <th class="p-4 sm:px-6 whitespace-nowrap">Reported By</th>
                            <th class="p-4 sm:px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($reports as $report)
                            <tr class="hover:bg-gray-50/80 transition duration-150" wire:key="report-{{ $report->id }}">
                                <td class="p-4 sm:px-6 text-gray-700 italic min-w-[250px]">
                                    "{{ Str::limit($report->post->content ?? 'Post was deleted', 80) }}"
                                </td>
                                <td class="p-4 sm:px-6 text-gray-500 text-sm whitespace-nowrap">
                                    {{ $report->reporter->name ?? 'System/Deleted User' }}
                                </td>
                                <td class="p-4 sm:px-6 flex justify-end space-x-4 whitespace-nowrap">
                                    <button wire:click="confirmFlag({{ $report->id }})" class="text-red-600 text-sm font-semibold hover:text-red-700 transition bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg">Confirm</button>
                                    <button wire:click="dismissFlag({{ $report->id }})" class="text-gray-500 text-sm font-medium hover:text-gray-800 transition px-3 py-1.5 hover:bg-gray-100 rounded-lg">Dismiss</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-10 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-3 text-green-200">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="text-sm font-medium text-gray-500">No flags to review.</p>
                                        <p class="text-xs mt-1">The community is behaving well!</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>