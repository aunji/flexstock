<div class="rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
    @if($path)
        <img src="{{ Storage::url($path) }}" alt="Payment Slip" class="w-full h-auto max-h-96 object-contain bg-gray-50 dark:bg-gray-900">
    @else
        <div class="p-4 text-center text-gray-500 dark:text-gray-400">
            No image available
        </div>
    @endif
</div>
