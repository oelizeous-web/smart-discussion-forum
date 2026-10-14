<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">


        <title>{{ $title ?? config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
<body class="font-sans antialiased text-slate-50" x-data>
        @if(session()->has('error'))
        <div class="fixed top-4 right-4 z-[100] bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl shadow-lg font-medium">
            {{ session('error') }}
        </div>
    @endif
    <div class="flex h-screen bg-slate-800 overflow-hidden">
        
        <!-- Sidebar -->
        @if(!request()->routeIs('quiz.attempt'))
            <!-- Mobile backdrop -->
            <div x-show="$store.ui.sidebarOpen" x-cloak @click="$store.ui.sidebarOpen = false"
                 x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/60 z-40 lg:hidden"></div>

            <!-- Green Sidebar -->
            <aside
                :class="$store.ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-50 w-64 h-full bg-green-700 border-r border-green-800 flex flex-col justify-between shrink-0 shadow-2xl transform transition-transform duration-200 ease-out lg:static lg:translate-x-0 lg:shadow-none">
                <div>
                    <div class="h-16 flex items-center justify-between px-6 border-b border-green-800">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group outline-none">
                        <img src="{{ asset('favicon.ico') }}" class="w-8 h-8 rounded-full group-hover:opacity-90 transition-opacity">
                        <span class="text-white font-bold text-lg tracking-wider group-hover:text-green-100 transition-colors">{{ config('app.name') }}</span>
                        </a>
                        <livewire:components.notification-bell />
                        <!-- Mobile close button -->
                        <button @click="$store.ui.sidebarOpen = false" type="button" aria-label="Close menu" class="lg:hidden text-green-200 hover:text-white p-1 -mr-2 rounded-md hover:bg-green-600 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <nav class="p-4 space-y-2 mt-4">
                        
                        <!-- Dashboard -->
                        <a href="{{ route('dashboard') }}" id="nav-dashboard" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('dashboard') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Dashboard</a>
                        
                        <!-- Student Links -->
                        @if(auth()->user()->hasAnyRole(['student']))
                            <a href="{{ route('forums') }}" id="nav-forums" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('forums') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Discussions</a>
                            <a href="{{ route('quizzes') }}" id="nav-quizzes-student" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('quizzes') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Quizzes</a>
                        @endif
                        
                        <!-- Lecturer Links -->
                        @if(auth()->user()->hasAnyRole(['lecturer']))
                            <a href="{{ route('lecturer.students') }}" id="nav-students" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('lecturer.students') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Students</a>
                            <a href="{{ route('lecturer.grades') }}" id="nav-grades" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('lecturer.grades') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Grades</a>
                            <a href="{{route('lecturer.quizzes')}}" iid="nav-quizzes" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('lecturer.quiz-control') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Quiz Control</a>
                              @endif
                        
                        <!-- Admin Links -->
                        @if(auth()->user()->hasAnyRole(['admin']))
                            <a href="{{ route('admin.members') }}" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('admin.members') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Members</a>
                            <a href="{{ route('admin.settings') }}" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('admin.settings') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Settings</a>
                        @endif
                        
                        <!-- Shared Links -->
                        @if(auth()->user()->hasAnyRole(['lecturer']))
                            <a href="{{route('reports')}}" id="nav-reports" @click="$store.ui.sidebarOpen = false" class="w-full flex items-center px-4 py-3 rounded-md transition-colors {{ request()->routeIs('reports.*') ? 'bg-green-900 text-white font-semibold shadow-inner' : 'text-green-100 hover:bg-green-600 hover:text-white' }}">Reports</a>
                        @endif  

                    </nav>
                </div>

                <!-- Profile Dropdown -->
                <div class="p-6 border-t border-green-800">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-3 w-full hover:opacity-80 transition">
                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center font-bold text-green-700 shrink-0 shadow">{{ substr(Auth::user()->name, 0, 1) }}</div>
                            <div class="flex flex-col text-left min-w-0">
                                <span class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</span>
                                <span class="text-xs text-green-200 truncate">Settings & Profile</span>
                            </div>
                        </button>
                        <div x-show="open" x-cloak @click.away="open = false" 
                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute bottom-16 left-0 w-full bg-green-900 border border-green-800 rounded-lg p-2 z-50 shadow-xl">
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-green-100 hover:bg-green-700 hover:text-white rounded transition">Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-300 hover:bg-red-600 hover:text-white rounded transition">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>
        @endif

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Mobile top bar with hamburger (Updated to match green theme) -->
            @if(!request()->routeIs('quiz.attempt'))
                <header class="h-16 flex items-center gap-3 px-4 border-b border-green-800 bg-green-700 shrink-0 lg:hidden">
                    <button @click="$store.ui.sidebarOpen = true" type="button" aria-label="Open menu" class="p-2 -ml-2 rounded-md text-green-100 hover:text-white hover:bg-green-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 outline-none">
                <img src="{{ asset('favicon.ico') }}" class="w-8 h-8 rounded-full group-hover:opacity-90 transition-opacity">
                <span class="text-white font-bold tracking-wider text-sm">{{ config('app.name') }}</span>
            </a>
            <livewire:components.notification-bell />
            </header>
            @endif
            <main class="flex-1 overflow-y-auto bg-slate-800">
                {{ $slot }}
            </main>
        </div>
    </div>
    
    <!-- Global Confirmation Modal -->
    <div x-data="{ 
            show: false, 
            title: '', 
            message: '', 
            callback: null 
        }" 
        @open-confirm.window="
            title = $event.detail.title;
            message = $event.detail.message;
            callback = $event.detail.callback;
            show = true;
        "
        x-show="show" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm">

        <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-sm w-full p-6 text-center shadow-2xl">
            <h2 class="text-xl font-bold text-white mb-2" x-text="title"></h2>
            <p class="text-slate-400 mb-6" x-text="message"></p>
            
            <div class="flex gap-3">
                <button @click="show = false" class="flex-1 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition">Cancel</button>
                <button @click="callback(); show = false;" class="flex-1 py-2 rounded-lg bg-green-600 text-white font-bold hover:bg-green-500 transition">Confirm</button>
            </div>
        </div>
    </div>
    @stack('scripts')
</body>
</html>