<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased bg-gray-50 dark:bg-[#121212] transition-colors duration-300">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            
            <!-- CUSTOM ICON / LOGO POSITION -->
            <div class="mb-8 flex flex-col items-center">
                <a href="/" wire:navigate class="flex flex-col items-center group">
                    
                    <!-- The Icon Wrapper -->
                    <div class="w-10 h-10 bg-[#5CC98B] rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform duration-200">
                        <img src="{{ asset('favicon.ico') }}" class="w-8 h-8 rounded-full group-hover:opacity-90 transition-opacity">
                    </div>

                    <!-- App Title -->
                    <span class="mt-4 text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">{{ config('app.name') }}</span>
                </a>
            </div>

            <!-- FORM CARD -->
            <div class="w-full sm:max-w-md px-8 py-8 bg-white dark:bg-[#1e1e1e] shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl transition-colors duration-300">
                {{ $slot }}
            </div>

        </div>
    </body>
</html>