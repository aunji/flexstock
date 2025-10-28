<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Current Company</h2>

            @if($this->getCurrentCompanyName())
                <div class="flex items-center space-x-3 mb-6">
                    <div class="flex-shrink-0">
                        <svg class="h-12 w-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">You are currently working in:</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->getCurrentCompanyName() }}</p>
                    </div>
                </div>
            @endif

            <div class="border-t dark:border-gray-700 pt-6">
                <h3 class="text-md font-medium mb-4">Switch to Another Company</h3>
                <form wire:submit.prevent="submit">
                    {{ $this->form }}
                </form>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Multi-Tenant Isolation</h3>
                    <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        All data you view and modify will be scoped to the selected company. Switching companies will update all resources, reports, and operations to reflect the new context.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
