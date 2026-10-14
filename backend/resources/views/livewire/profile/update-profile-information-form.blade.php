<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

use function Livewire\Volt\uses;
use function Livewire\Volt\state;

// 1. Tell Volt to use the file uploads trait
uses([WithFileUploads::class]);

// 2. Add 'bio' and 'newAvatar' to your state
state([
    'name' => fn () => auth()->user()->name,
    'email' => fn () => auth()->user()->email,
    'bio' => fn () => auth()->user()->bio,
    'newAvatar' => null, 
]);

$updateProfileInformation = function () {
    $user = Auth::user();

    // 3. Add validation for the new fields
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        'bio' => ['nullable', 'string', 'max:1000'],
        'newAvatar' => ['nullable', 'image', 'max:2048'], // Restricts to images under 2MB
    ]);

    $user->fill([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'bio' => $validated['bio'],
    ]);

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    // 4. Handle the avatar upload
    if ($this->newAvatar) {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar); // Delete old avatar
        }
        $user->avatar = $this->newAvatar->store('avatars', 'public'); // Save new one
    }

    $user->save();

    $this->dispatch('profile-updated', name: $user->name);
};

$sendVerification = function () {
    $user = Auth::user();

    if ($user->hasVerifiedEmail()) {
        $this->redirectIntended(default: route('dashboard', absolute: false));
        return;
    }

    $user->sendEmailVerificationNotification();
    Session::flash('status', 'verification-link-sent');
};
?>
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information, email address, and profile picture.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        
        <!-- 1. New Avatar Upload Section -->
        <div>
            <x-input-label for="avatar" :value="__('Profile Picture')" />
            <div class="mt-2 flex items-center gap-4">
                
                <!-- Avatar Preview -->
                <div class="shrink-0 h-16 w-16 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center border border-gray-300 dark:border-gray-700">
                    @if ($newAvatar)
                        <!-- Shows immediately after user selects a file -->
                        <img class="h-full w-full object-cover" src="{{ $newAvatar->temporaryUrl() }}" alt="Preview">
                    @elseif (auth()->user()->avatar)
                        <!-- Shows the saved database image -->
                        <img class="h-full w-full object-cover" src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="Avatar">
                    @else
                        <!-- Fallback to the first letter of their name -->
                        <span class="text-gray-500 dark:text-gray-400 text-xl font-bold">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </span>
                    @endif
                </div>

                <!-- File Input styling using Tailwind's file modifiers -->
                <input type="file" wire:model="newAvatar" id="avatar" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-green-50 file:text-green-700 dark:file:bg-green-900 dark:file:text-green-300
                    hover:file:bg-green-100 dark:hover:file:bg-green-800 cursor-pointer transition
                " />
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('newAvatar')" />
        </div>

        <!-- 2. Existing Name Field -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <!-- 3. Existing Email Field -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- 4. New Bio Field -->
        <div>
            <x-input-label for="bio" :value="__('Bio')" />
            <!-- Using standard Tailwind classes that perfectly match Breeze's x-text-input -->
            <textarea wire:model="bio" id="bio" name="bio" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-green-500 dark:focus:border-green-600 focus:ring-green-500 dark:focus:ring-green-600 rounded-md shadow-sm"></textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <!-- Loading indicator for the image upload -->
            <span wire:loading wire:target="newAvatar" class="text-sm text-gray-500">Uploading...</span>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
