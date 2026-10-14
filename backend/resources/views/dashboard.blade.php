<x-app-layout>

    <div class="max-w-7xl">
        @role('admin')
            <livewire:admin.dashboard-panel />
        @endrole

        @role('lecturer')
            <livewire:lecturer.dashboard-panel />
        @endrole

        @role('student')
            <livewire:student.dashboard-panel />
        @endrole

        @unlessrole('admin|lecturer|student')
            <div class="bg-red-900/50 border border-red-500 rounded-2xl p-6 text-red-200 mt-8">
                Your account is pending role assignment. Please contact an administrator.
            </div>
        @endunlessrole
    </div>
</x-app-layout>