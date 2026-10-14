<?php

use Livewire\Volt\Component;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    // Configuration Properties
    public $inactivity_days;
    public $blacklist_duration;
    public $platform_rules;

    public function mount()
    {
        // Enforce strict access control: Only Administrators can access this component
        if (!Auth::check() || !Auth::user()->hasRole('admin')) {
                    abort(403, 'UNAUTHORIZED ACTION. INTERFACE ACCESS RESTRICTED TO ADMINISTRATORS.');
                }

        // Load existing configurations or set system defaults
        $this->inactivity_days = Setting::get('inactivity_days', 14);
        $this->blacklist_duration = Setting::get('blacklist_duration', 7);
        $this->platform_rules = Setting::get('platform_rules', "1. Be respectful to all members.\n2. No spam or irrelevant content.\n3. Use the platform for academic discussions.");
    }

    public function saveSettings()
    {
        // Re-verify authorization before processing the write operation
        if (!Auth::check() || !Auth::user()->hasRole('admin')) {
                    abort(403, 'UNAUTHORIZED ACTION. INTERFACE ACCESS RESTRICTED TO ADMINISTRATORS.');
                }

        // Validate administrator inputs
        $this->validate([
            'inactivity_days' => 'required|integer|min:1',
            'blacklist_duration' => 'required|integer|min:1',
            'platform_rules' => 'required|string|min:10',
        ]);

        // Persist the configurations to the database
        Setting::set('inactivity_days', $this->inactivity_days);
        Setting::set('blacklist_duration', $this->blacklist_duration);
        Setting::set('platform_rules', $this->platform_rules);
        
        session()->flash('success', 'Platform configurations updated successfully.');
    }
}; ?>

<div class="p-4 sm:p-6 lg:p-8 bg-gray-50 min-h-screen text-gray-900 font-sans">
    
    <!-- Header Area -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">System Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Configure automated enforcement thresholds and platform onboarding rules.</p>
    </div>

    <!-- Success Feedback Notification -->
    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl shadow-sm relative mb-6 flex items-center" role="alert" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2 text-green-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="block sm:inline text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
        
        <!-- Automated Moderation Panel -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-6 border-b border-gray-100 pb-3">Automation & Enforcement</h2>
            
            <div class="space-y-6">
                <div>
                    <label class="block mb-1.5 font-semibold text-gray-700 text-sm">Inactivity Threshold (Days)</label>
                    <input type="number" wire:model="inactivity_days" class="w-full bg-white border border-gray-300 text-gray-900 p-2.5 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 transition sm:text-sm">
                    <p class="text-xs text-gray-500 mt-2">The duration a user must be inactive before the system issues the 2 automated warnings.</p>
                    @error('inactivity_days') <span class="text-red-600 font-medium text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block mb-1.5 font-semibold text-gray-700 text-sm">Blacklist Duration (Days)</label>
                    <input type="number" wire:model="blacklist_duration" class="w-full bg-white border border-gray-300 text-gray-900 p-2.5 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 transition sm:text-sm">
                    <p class="text-xs text-gray-500 mt-2">The configured amount of time a member is automatically blacklisted after failing to comply with warnings.</p>
                    @error('blacklist_duration') <span class="text-red-600 font-medium text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- Onboarding & Compliance Panel -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-6 border-b border-gray-100 pb-3">Onboarding & Registration</h2>
            
            <div class="space-y-6">
                <div>
                    <label class="block mb-1.5 font-semibold text-gray-700 text-sm">Platform Rules & Guidelines</label>
                    <textarea wire:model="platform_rules" rows="8" class="w-full bg-white border border-gray-300 text-gray-900 p-3 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 transition sm:text-sm resize-y"></textarea>
                    <p class="text-xs text-gray-500 mt-2">Instructions guiding new members on the rules of the platform. If they agree, they are registered; otherwise, they are declined.</p>
                    @error('platform_rules') <span class="text-red-600 font-medium text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

    </div>

    <!-- Save Action -->
    <div class="mt-8 flex justify-end">
        <button wire:click="saveSettings" wire:loading.attr="disabled" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-xl shadow-sm transition flex items-center font-medium text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed">
            <svg wire:loading wire:target="saveSettings" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Save Configurations
        </button>
    </div>
</div>