<?php

use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public function register() {
        // 1. Validate the input
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
        ]);

        // 2. Wrap in a transaction so if role assignment fails, it rolls back the user creation
        DB::transaction(function () {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'status' => 'ACTIVE',
            ]);
            
            $user->assignRole('lecturer');
        });

        // 3. Reset form fields
        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        
        // 4. Redirect to the same page to fresh the Livewire state and close the modal automatically
        return $this->redirect('/members', navigate: true);
    }
}; ?>

<div class="p-6 bg-slate-800 text-white rounded-2xl shadow-2xl border border-slate-700">
    
    <!-- Modal Header -->
    <div class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4">
        <h2 class="text-xl font-bold">Register Faculty Member</h2>
        <button @click="showModal = false" type="button" class="text-slate-400 hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    
    <!-- Registration Form -->
    <form wire:submit="register" class="space-y-4">
        
        <!-- Name Input -->
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Full Name</label>
            <input wire:model="name" type="text" placeholder="e.g. Dr. John Doe" class="w-full p-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-green-500 focus:border-green-500 transition-colors">
            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Email Input -->
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
            <input wire:model="email" type="email" placeholder="lecturer@university.edu" class="w-full p-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-green-500 focus:border-green-500 transition-colors">
            @error('email') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Password Input -->
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
            <input wire:model="password" type="password" placeholder="••••••••" class="w-full p-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-green-500 focus:border-green-500 transition-colors">
            @error('password') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Confirm Password Input -->
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Confirm Password</label>
            <input wire:model="password_confirmation" type="password" placeholder="••••••••" class="w-full p-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-green-500 focus:border-green-500 transition-colors">
        </div>
        
        <!-- Submit Button -->
        <div class="pt-4">
            <button type="submit" class="w-full bg-green-600 hover:bg-green-500 py-3 rounded-lg font-bold transition flex justify-center items-center shadow-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">Register Lecturer</span>
                <svg wire:loading wire:target="register" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>
        
    </form>
</div>