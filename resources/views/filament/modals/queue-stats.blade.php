<div class="p-4">
    <div class="grid grid-cols-2 gap-4">
        @foreach($stats as $label => $value)
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</div>
            </div>
        @endforeach
    </div>
</div>