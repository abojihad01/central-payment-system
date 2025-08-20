<div class="space-y-4">
    @php
        $checks = [
            'Laravel Scheduler' => shell_exec('ps aux | grep "schedule:run" | grep -v grep') ? 'Running' : 'Not Running',
            'Queue Workers' => shell_exec('ps aux | grep "queue:work" | grep -v grep') ? 'Running' : 'Not Running',
            'Crontab' => shell_exec('crontab -l 2>/dev/null') ? 'Configured' : 'Empty',
            'Database' => \DB::connection()->getPdo() ? 'Connected' : 'Disconnected',
            'Storage Writable' => is_writable(storage_path()) ? 'Yes' : 'No',
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'Memory Usage' => number_format(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'Disk Space' => number_format(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB free',
        ];
        
        $queueStats = \DB::table('jobs')->selectRaw('
            COUNT(*) as total,
            COUNT(CASE WHEN reserved_at IS NULL THEN 1 END) as pending,
            COUNT(CASE WHEN reserved_at IS NOT NULL THEN 1 END) as processing,
            COUNT(CASE WHEN attempts > 0 THEN 1 END) as failed_attempts
        ')->first();
        
        $cronJobs = \App\Models\CronJob::selectRaw('
            COUNT(*) as total,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
            COUNT(CASE WHEN next_run_at < NOW() AND is_active = 1 THEN 1 END) as overdue
        ')->first();
    @endphp

    <div class="grid grid-cols-2 gap-4">
        @foreach($checks as $check => $status)
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex justify-between items-center">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $check }}</div>
                    <div class="text-sm {{ 
                        str_contains($status, 'Running') || str_contains($status, 'Connected') || str_contains($status, 'Yes') || str_contains($status, 'Configured') 
                        ? 'text-green-600' 
                        : (str_contains($status, 'Not') || str_contains($status, 'No') || str_contains($status, 'Empty') || str_contains($status, 'Disconnected') 
                            ? 'text-red-600' 
                            : 'text-gray-600') 
                    }}">
                        {{ $status }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Queue Statistics</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Jobs:</span>
                    <span class="text-sm font-medium">{{ $queueStats->total ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Pending:</span>
                    <span class="text-sm font-medium text-yellow-600">{{ $queueStats->pending ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Processing:</span>
                    <span class="text-sm font-medium text-blue-600">{{ $queueStats->processing ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Failed Attempts:</span>
                    <span class="text-sm font-medium text-red-600">{{ $queueStats->failed_attempts ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Cron Jobs</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Jobs:</span>
                    <span class="text-sm font-medium">{{ $cronJobs->total ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active:</span>
                    <span class="text-sm font-medium text-green-600">{{ $cronJobs->active ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Overdue:</span>
                    <span class="text-sm font-medium text-red-600">{{ $cronJobs->overdue ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    @php
        $recentLogs = \DB::table('admin_logs')->orderBy('created_at', 'desc')->limit(5)->get();
    @endphp

    @if($recentLogs->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Recent Activity</h3>
            <div class="space-y-2">
                @foreach($recentLogs as $log)
                    <div class="text-sm">
                        <span class="text-gray-500">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}:</span>
                        <span class="text-gray-900 dark:text-white">{{ $log->action ?? 'System Activity' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>