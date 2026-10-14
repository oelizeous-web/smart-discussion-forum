<?php

use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

// Note: We completely remove the default layout wrapper here to take total control over the CSS canvas
new #[Layout('components.layouts.auth.simple')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $agree_to_rules = false; 

    /**
     * Handle an incoming registration request.
     */
    public function register(RegistrationService $registrationService): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'agree_to_rules' => ['accepted'],
        ], [
            'agree_to_rules.accepted' => 'You must agree to the platform rules to create an account.',
        ]);
        // registration gets the 'student' role and agreed_to_rules=true too.
        $validated['agreed_to_rules'] = $validated['agree_to_rules'];

        $user = $registrationService->registerUser($validated, 'student');

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>
<!-- Main Application Window Wrapper -->
<div x-data="{ showRules: false }" class="w-full max-w-md mx-auto my-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-xl rounded-2xl overflow-hidden font-sans">

    <!-- 1. Brand Bar (green, matches the login screen's accent) -->
    <div class="w-full bg-[#2F7A54] text-white px-5 py-3 text-xs font-semibold tracking-wide select-none flex items-center gap-2">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 4L3 8L12 12L21 8L12 4Z" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M6 10.5V16C6 16 8.5 18 12 18C15.5 18 18 16 18 16V10.5" stroke="white" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>{{ config('app.name') }}</span>
    </div>

    <!-- 2. Icon Badge + Heading -->
    <div class="w-full bg-zinc-50 dark:bg-zinc-950 pt-8 pb-6 px-6 border-b border-zinc-200 dark:border-zinc-800 flex flex-col items-center text-center">
        <div class="w-14 h-14 rounded-2xl bg-[#5CC98B] flex items-center justify-center mb-4 shadow-sm">
            <img src="{{ asset('favicon.ico') }}" class="w-8 h-8 rounded-full group-hover:opacity-90 transition-opacity">
        </div>
        <h1 class="text-xl font-bold tracking-tight text-zinc-950 dark:text-white">
            Create an account
        </h1>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-1">
            Join {{ config('app.name') }}
        </p>
    </div>

    <!-- 3. Registration Form -->
    <div class="w-full bg-white dark:bg-zinc-900 p-6 md:p-7">

        <!-- Form Validation Alerts -->
        @if ($errors->any())
            <div class="mb-4 text-xs text-red-600 space-y-1 bg-red-50 dark:bg-red-950/20 p-3 rounded-lg border border-red-200 dark:border-red-900">
                @foreach ($errors->all() as $error)
                    <p>• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form wire:submit="register" id="registrationForm" class="flex flex-col gap-4">
            <!-- Full Name -->
            <div class="flex flex-col gap-1">
                <label for="name" class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Full Name</label>
                <flux:input wire:model="name" id="name" type="text" name="name" required autofocus autocomplete="name"
                    class="!py-1 !rounded-lg focus:!border-[#2F7A54] focus:!ring-[#2F7A54]" />
            </div>

            <!-- Email Address -->
            <div class="flex flex-col gap-1">
                <label for="email" class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Email Address</label>
                <flux:input wire:model="email" id="email" type="email" name="email" required autocomplete="email"
                    class="!py-1 !rounded-lg focus:!border-[#2F7A54] focus:!ring-[#2F7A54]" />
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-1">
                <label for="password" class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Password</label>
                <flux:input wire:model="password" id="password" type="password" name="password" required autocomplete="new-password" viewable
                    class="!rounded-lg focus:!border-[#2F7A54] focus:!ring-[#2F7A54]" />
            </div>

            <!-- Confirm Password -->
            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Confirm Password</label>
                <flux:input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="!py-1 !rounded-lg focus:!border-[#2F7A54] focus:!ring-[#2F7A54]" />
            </div>

            <!-- Checkbox + Rules Link -->
            <div class="flex items-start gap-2 px-1 mt-1">
                <input 
                    wire:model="agree_to_rules" 
                    id="agree_to_rules" 
                    type="checkbox" 
                    name="agree_to_rules"
                    class="w-4 h-4 mt-0.5 shrink-0 rounded-md text-[#2F7A54] border-zinc-300 focus:ring-2 focus:ring-[#5CC98B] focus:ring-offset-1 dark:bg-zinc-900 dark:border-zinc-700"
                    required
                />
                <label for="agree_to_rules" class="text-xs font-medium text-zinc-700 dark:text-zinc-300 select-none cursor-pointer leading-relaxed">
                    I agree to the
                    <button type="button"
                       @click.stop.prevent="showRules = true"
                       class="underline decoration-2 underline-offset-2 decoration-[#2F7A54]/40 hover:decoration-[#2F7A54] text-[#2F7A54] dark:text-[#5CC98B] font-semibold transition">
                        platform rules
                    </button>
                    and terms
                </label>
            </div>

            <!-- Registration Button (green, matches login) -->
            <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 bg-[#2F7A54] hover:bg-[#256242] active:bg-[#1e4f36] text-white py-3 text-sm font-semibold tracking-wide rounded-xl shadow-sm hover:shadow-md transition-all block border-none text-center cursor-pointer mt-1 focus:outline-none focus:ring-2 focus:ring-[#5CC98B] focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                Register
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12H19" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M13 6L19 12L13 18" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>

        <!-- Login Redirect Link -->
        <div class="mt-6 text-xs text-center text-zinc-500 dark:text-zinc-400">
            Already have an account? 
            <x-text-link href="{{ route('login') }}" class="text-[#2F7A54] hover:text-[#256242] dark:text-[#5CC98B] hover:underline ml-0.5 font-semibold !text-[#2F7A54] dark:!text-[#5CC98B]">
                Login here
            </x-text-link>
        </div>
    </div>

    <!-- Platform Rules Modal (lives in this file, no separate route needed) -->
    <div x-show="showRules"
         x-cloak
         x-transition.opacity
         @keydown.escape.window="showRules = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display: none;">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-zinc-950/60" @click="showRules = false"></div>

        <!-- Modal Card -->
        <div x-show="showRules"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-2xl p-6">

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#5CC98B]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 3.5L19 6.2V11C19 15.2 16.1 19 12 20.5C7.9 19 5 15.2 5 11V6.2L12 3.5Z" stroke="#1e1e1e" stroke-width="1.6" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <h3 class="font-bold text-base text-zinc-950 dark:text-white tracking-wide">
                        Platform Rules
                    </h3>
                </div>
                <button type="button" @click="showRules = false"
                        class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition"
                        aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <ul class="space-y-3 text-sm text-zinc-700 dark:text-zinc-200 leading-relaxed">
                <li class="flex gap-2">
                    <span class="text-[#2F7A54] font-bold">•</span>
                    Be respectful to all members
                </li>
                <li class="flex gap-2">
                    <span class="text-[#2F7A54] font-bold">•</span>
                    No spam or irrelevant content
                </li>
                <li class="flex gap-2">
                    <span class="text-[#2F7A54] font-bold">•</span>
                    Use the platform for academic discussions
                </li>
                <li class="flex gap-2">
                    <span class="text-[#2F7A54] font-bold">•</span>
                    Violation may lead to warnings or bans
                </li>
            </ul>

            <button type="button" @click="showRules = false"
                    class="w-full mt-6 border border-[#2F7A54] text-[#2F7A54] dark:text-[#5CC98B] dark:border-[#5CC98B] hover:bg-[#2F7A54] hover:text-white dark:hover:bg-[#2F7A54] py-2.5 text-xs font-semibold tracking-wide rounded-lg transition-all">
                Close
            </button>
        </div>
    </div>
</div>