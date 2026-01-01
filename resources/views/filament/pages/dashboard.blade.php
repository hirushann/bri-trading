<x-filament-panels::page>
    <!-- <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">DEBUG:</strong>
        <span class="block sm:inline">Custom Dashboard View is Loaded.</span>
    </div> -->
    <div class="flex w-full gap-6">
        <div class="w-full">
            @livewire(\App\Filament\Widgets\DashboardStats::class)
        </div>
        <div class="w-full flex flex-col gap-6">
            @livewire(\App\Filament\Widgets\PendingCheques::class)
            @livewire(\App\Filament\Widgets\OrderChart::class)
        </div>
    </div>
</x-filament-panels::page>
