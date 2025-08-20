<div class="space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Webhook Configuration
                </h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                    <p>Configure the following webhook endpoint in your Stripe dashboard:</p>
                    <code class="bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded mt-1 inline-block">
                        {{ url('/webhooks/stripe') }}
                    </code>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg border overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Recent Webhook Events
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Latest webhook events received from payment gateways
            </p>
        </div>

        <div class="overflow-x-auto">
            @php
                $webhookLogs = \DB::table('webhook_logs')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
            @endphp

            @if($webhookLogs->isEmpty())
                <div class="px-6 py-8 text-center">
                    <div class="text-gray-400 text-4xl mb-2">📡</div>
                    <p class="text-gray-500 dark:text-gray-400">No webhook events found</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                        Webhook events will appear here once configured
                    </p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Event Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Payment ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Received
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($webhookLogs as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $log->event_type ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->payment_id ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusClass = match($log->status ?? 'unknown') {
                                            'processed' => 'bg-green-100 text-green-800',
                                            'failed' => 'bg-red-100 text-red-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ $log->status ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('M d, H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button 
                                        onclick="showWebhookDetails({{ json_encode($log) }})"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Webhook Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Events</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ \DB::table('webhook_logs')->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Processed Today</div>
            <div class="text-2xl font-bold text-green-600">
                {{ \DB::table('webhook_logs')->where('status', 'processed')->whereDate('created_at', today())->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Failed Today</div>
            <div class="text-2xl font-bold text-red-600">
                {{ \DB::table('webhook_logs')->where('status', 'failed')->whereDate('created_at', today())->count() }}
            </div>
        </div>
    </div>
</div>

<script>
function showWebhookDetails(log) {
    // Create a modal or detailed view for webhook log details
    alert('Webhook Details:\n\n' + 
          'Event Type: ' + (log.event_type || 'Unknown') + '\n' +
          'Payment ID: ' + (log.payment_id || 'N/A') + '\n' +
          'Status: ' + (log.status || 'Unknown') + '\n' +
          'Created: ' + log.created_at + '\n\n' +
          'Raw Data: ' + (log.raw_data || 'No data available'));
}
</script>