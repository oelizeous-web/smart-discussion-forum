<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|source-serif-4:500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --paper: #F7F5EE;      /* warm academic off-white, not pure white */
            --paper-card: #FFFFFF;
            --ink: #232A22;        /* deep ink with a hint of green */
            --ink-soft: #5B6255;   /* muted warm gray-green for body copy */
            --hairline: #E4E0D3;   /* parchment border */
            --brand: #5CC98B;      /* existing brand green (light accents) */
            --brand-deep: #2F7A54; /* deeper academic green (buttons/links) */
            --brand-deep-hover: #256242;
        }
        .dark {
            --ink-soft: #9CA69C;
        }
        .font-serif-display { font-family: "Source Serif 4", Georgia, serif; }
    </style>
</head>
<body class="antialiased flex flex-col min-h-screen transition-colors duration-300" style="background-color: var(--paper); color: var(--ink);">

    <div class="dark:bg-[#121212] dark:text-gray-100 flex flex-col min-h-screen w-full transition-colors duration-300">

    <!-- Top bar -->
    <header class="w-full border-b dark:border-gray-800" style="border-color: var(--hairline);">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="flex items-center justify-center w-9 h-9 rounded-md text-white" style="background-color: var(--brand-deep);">
                        <img src="{{ asset('favicon.ico') }}" class="w-8 h-8 rounded-full group-hover:opacity-90 transition-opacity">
                </span>
                <span class="font-semibold tracking-tight text-[15px]" style="color: var(--ink);">{{ config('app.name') }}</span>
            </div>

            @if (Route::has('login'))
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-4 py-2 rounded-md text-sm font-semibold text-white transition ease-in-out duration-150" style="background-color: var(--brand-deep);" onmouseover="this.style.backgroundColor='var(--brand-deep-hover)'" onmouseout="this.style.backgroundColor='var(--brand-deep)'">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-md text-sm font-semibold transition ease-in-out duration-150 hover:opacity-70 dark:text-gray-200" style="color: var(--ink);">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-4 py-2 rounded-md text-sm font-semibold text-white transition ease-in-out duration-150" style="background-color: var(--brand-deep);" onmouseover="this.style.backgroundColor='var(--brand-deep-hover)'" onmouseout="this.style.backgroundColor='var(--brand-deep)'">
                                Register
                            </a>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    </header>

    <!-- Main Hero Section -->
    <main class="flex-grow flex flex-col items-center justify-center px-4 text-center pt-20 pb-16">

        <!-- Eyebrow badge -->
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full mb-7 text-xs font-semibold tracking-wide uppercase dark:bg-gray-800 dark:text-gray-300"
             style="background-color: rgba(92,201,139,0.14); color: var(--brand-deep);">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--brand);"></span>
            Academic Community Platform
        </div>

        <h1 class="font-serif-display text-4xl md:text-6xl font-semibold mb-6 tracking-tight" style="color: var(--ink);">
            Welcome to <br class="hidden sm:block" />
            <span style="color: var(--brand-deep);">{{ config('app.name') }}</span>
        </h1>

        <p class="text-lg md:text-xl mb-10 max-w-2xl mx-auto" style="color: var(--ink-soft);">
            Share, learn, and grow together. Join our academic community to engage in meaningful discussions, access valuable resources, and connect with peers and lecturers.
        </p>

        <!-- Centered Authentication Buttons -->
        @if (Route::has('login'))
            <div class="flex flex-col sm:flex-row gap-4 justify-center w-full max-w-md mx-auto">
                @auth
                    <a href="{{ url('/dashboard') }}" class="w-full px-8 py-3 rounded-md font-semibold text-white transition ease-in-out duration-150 shadow-sm" style="background-color: var(--brand-deep);" onmouseover="this.style.backgroundColor='var(--brand-deep-hover)'" onmouseout="this.style.backgroundColor='var(--brand-deep)'">
                        Go to Dashboard
                    </a>
                @endauth
            </div>
        @endif
    </main>

    <!-- Professional Features Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 w-full">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Feature Card 1 -->
            <div class="p-7 rounded-xl border dark:bg-[#1e1e1e] dark:border-gray-800 transition-all duration-200 hover:-translate-y-0.5"
                 style="background-color: var(--paper-card); border-color: var(--hairline); box-shadow: 0 1px 2px rgba(35,42,34,0.04);">
                <div class="w-11 h-11 rounded-lg flex items-center justify-center mb-5" style="background-color: rgba(92,201,139,0.14);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5.5C4 4.67 4.67 4 5.5 4H11V20H5.5C4.67 20 4 19.33 4 18.5V5.5Z" stroke="#2F7A54" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M20 5.5C20 4.67 19.33 4 18.5 4H13V20H18.5C19.33 20 20 19.33 20 18.5V5.5Z" stroke="#2F7A54" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M11 7H8.5" stroke="#2F7A54" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M11 10H8.5" stroke="#2F7A54" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2 dark:text-gray-100" style="color: var(--ink);">Academic Discussions</h3>
                <p class="text-sm leading-relaxed dark:text-gray-400" style="color: var(--ink-soft);">Engage in organized topics strictly moderated to ensure quality learning and continuous academic growth.</p>
            </div>

            <!-- Feature Card 2 -->
            <div class="p-7 rounded-xl border dark:bg-[#1e1e1e] dark:border-gray-800 transition-all duration-200 hover:-translate-y-0.5"
                 style="background-color: var(--paper-card); border-color: var(--hairline); box-shadow: 0 1px 2px rgba(35,42,34,0.04);">
                <div class="w-11 h-11 rounded-lg flex items-center justify-center mb-5" style="background-color: rgba(92,201,139,0.14);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3L3 7.5L12 12L21 7.5L12 3Z" stroke="#2F7A54" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M7 9.7V14.5C7 14.5 9.2 16.5 12 16.5C14.8 16.5 17 14.5 17 14.5V9.7" stroke="#2F7A54" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 7.5V13" stroke="#2F7A54" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2 dark:text-gray-100" style="color: var(--ink);">Connect with Lecturers</h3>
                <p class="text-sm leading-relaxed dark:text-gray-400" style="color: var(--ink-soft);">Get direct insights, feedback, and guidance from authorized university staff members and professors.</p>
            </div>

            <!-- Feature Card 3 -->
            <div class="p-7 rounded-xl border dark:bg-[#1e1e1e] dark:border-gray-800 transition-all duration-200 hover:-translate-y-0.5"
                 style="background-color: var(--paper-card); border-color: var(--hairline); box-shadow: 0 1px 2px rgba(35,42,34,0.04);">
                <div class="w-11 h-11 rounded-lg flex items-center justify-center mb-5" style="background-color: rgba(92,201,139,0.14);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3.5L19 6.2V11C19 15.2 16.1 19 12 20.5C7.9 19 5 15.2 5 11V6.2L12 3.5Z" stroke="#2F7A54" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M9 11.3L11.1 13.4L15.2 9.2" stroke="#2F7A54" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2 dark:text-gray-100" style="color: var(--ink);">Secure &amp; Moderated</h3>
                <p class="text-sm leading-relaxed dark:text-gray-400" style="color: var(--ink-soft);">A safe platform equipped with clear community rules, ensuring a respectful environment for all members.</p>
            </div>

        </div>
    </section>

    <!-- Simple Footer -->
    <footer class="dark:bg-[#0a0a0a] py-6 mt-auto border-t dark:border-gray-800 text-center text-sm dark:text-gray-500 transition-colors duration-300"
             style="background-color: #F1EEE4; border-color: var(--hairline); color: var(--ink-soft);">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </footer>

    </div>
</body>
</html>