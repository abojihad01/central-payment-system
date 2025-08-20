<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Runs</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $record->run_count }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Failures</div>
            <div class="text-2xl font-bold {{ $record->failure_count > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $record->failure_count }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Success Rate</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($record->success_rate, 1) }}%</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Last Run</div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->last_run_formatted }}</div>
        </div>
    </div>

    @if($record->last_output)
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Last Output</h3>
            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-auto text-sm max-h-64">{{ $record->last_output }}</pre>
        </div>
    @endif

    @if($record->last_error)
        <div>
            <h3 class="text-lg font-medium text-red-600 mb-2">Last Error</h3>
            <pre class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg overflow-auto text-sm max-h-64 text-red-700 dark:text-red-400">{{ $record->last_error }}</pre>
        </div>
    @endif

    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Job Configuration</h3>
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium">Command:</span>
                    <pre class="mt-1 text-gray-600 dark:text-gray-400">{{ $record->command }}</pre>
                </div>
                <div>
                    <span class="font-medium">Schedule:</span>
                    <span class="mt-1 block text-gray-600 dark:text-gray-400">{{ $record->cron_expression }}</span>
                </div>
                <div>
                    <span class="font-medium">Timeout:</span>
                    <span class="mt-1 block text-gray-600 dark:text-gray-400">{{ $record->timeout_seconds }}s</span>
                </div>
                <div>
                    <span class="font-medium">Next Run:</span>
                    <span class="mt-1 block text-gray-600 dark:text-gray-400">{{ $record->next_run_formatted }}</span>
                </div>
            </div>
        </div>
    </div>
</div>