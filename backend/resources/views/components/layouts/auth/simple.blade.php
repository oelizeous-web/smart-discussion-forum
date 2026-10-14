<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
   <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    
    <!-- This container is updated from max-w-sm to max-w-4xl to accommodate your side-by-side design -->
    <div class="w-full max-w-4xl mx-auto flex flex-col gap-4">
        
        <!-- Main Component Insertion Slot -->
       
            {{ $slot }}
        </div>
        
   

    @fluxScripts
</body>

</html>
