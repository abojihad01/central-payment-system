<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Scheduler Status -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Scheduler Status</div>
                        <div class="text-lg font-semibold {{ $isRunning ? 'text-green-600' : 'text-red-600' }}">
                            {{ $isRunning ? 'Running' : 'Stopped' }}
                        </div>
                    </div>
                    <div class="text-2xl">
                        @if($isRunning)
                            <span class="text-green-600">●</span>
                        @else
                            <span class="text-red-600">●</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Tasks</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ count($scheduledTasks) }}</div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="text-sm text-gray-500 dark:text-gray-400">Last Run</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $lastRun }}</div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="text-sm text-gray-500 dark:text-gray-400">Next Run</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $nextRun }}</div>
            </div>
        </div>

        <!-- Refresh Button -->
        <div class="flex justify-end">
            <button wire:click="refresh" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Refresh Data
            </button>
        </div>

        <!-- Scheduled Tasks Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Scheduled Tasks</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Command
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Schedule
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Next Due
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Environment
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Options
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($scheduledTasks as $task)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ Str::limit($task['command'], 50) }}
                                    </div>
                                    @if($task['description'] !== 'No description')
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $task['description'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ $task['expression'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($task['next_due'])->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @foreach($task['environments'] as $env)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 mr-1">
                                            {{ $env }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex space-x-2">
                                        @if($task['without_overlapping'])
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                No Overlap
                                            </span>
                                        @endif
                                        @if($task['on_one_server'])
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                One Server
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No scheduled tasks found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cron Setup Instructions -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Cron Setup Required
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>To enable automatic scheduling, add this line to your server's crontab:</p>
                        <code class="bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded mt-2 block">
                            * * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1
                        </code>
                        <p class="mt-2">This will run the Laravel scheduler every minute.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>