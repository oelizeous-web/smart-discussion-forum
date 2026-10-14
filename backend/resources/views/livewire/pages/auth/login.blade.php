<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    if (! $this->form->authenticate()) {
        return redirect()->route('account.blacklisted');
    }

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email"
                class="block mt-1.5 w-full rounded-lg border-gray-300 dark:border-gray-700 focus:border-[#2F7A54] focus:ring-[#2F7A54]"
                type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password"
                class="block mt-1.5 w-full rounded-lg border-gray-300 dark:border-gray-700 focus:border-[#2F7A54] focus:ring-[#2F7A54]"
                type="password"
                name="password"
                required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center cursor-pointer select-none">
                <input wire:model="form.remember" id="remember" type="checkbox"
                    class="w-4 h-4 rounded-md dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-[#2F7A54] shadow-sm focus:ring-2 focus:ring-[#5CC98B] focus:ring-offset-1 dark:focus:ring-offset-gray-800 transition"
                    name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-[#2F7A54] dark:text-[#5CC98B] hover:text-[#256242] dark:hover:text-[#4ab879] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#5CC98B] dark:focus:ring-offset-gray-800 transition"
                    href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <!-- Styled Login Button -->
        <button type="submit"
            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#2F7A54] border border-transparent rounded-xl font-semibold text-sm text-white hover:bg-[#256242] focus:bg-[#256242] active:bg-[#1e4f36] focus:outline-none focus:ring-2 focus:ring-[#5CC98B] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-sm hover:shadow-md">
            {{ __('Log in') }}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform group-hover:translate-x-0.5">
                <path d="M5 12H19" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M13 6L19 12L13 18" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </form>
</div>