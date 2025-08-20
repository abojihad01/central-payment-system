<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Health Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Pending Payments</div>
                        <div class="text-2xl font-bold {{ $healthStats['total_pending'] > 50 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                            {{ $healthStats['total_pending'] ?? 0 }}
                        </div>
                    </div>
                    <div class="text-2xl text-blue-600">💳</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Stuck Payments</div>
                        <div class="text-2xl font-bold {{ ($healthStats['stuck_payments'] ?? 0) > 10 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $healthStats['stuck_payments'] ?? 0 }}
                        </div>
                    </div>
                    <div class="text-2xl">⚠️</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Success Rate (24h)</div>
                        <div class="text-2xl font-bold {{ ($healthStats['success_rate_24h'] ?? 0) < 90 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format($healthStats['success_rate_24h'] ?? 0, 1) }}%
                        </div>
                    </div>
                    <div class="text-2xl">📈</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Lost Payments Found</div>
                        <div class="text-2xl font-bold {{ count($lostPayments) > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ count($lostPayments) }}
                        </div>
                    </div>
                    <div class="text-2xl">🔍</div>
                </div>
            </div>
        </div>

        <!-- Refresh Button -->
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Last Scan: {{ $healthStats['last_recovery_run'] ?? 'Never' }}
            </div>
            <button wire:click="refresh" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                تحديث البيانات
            </button>
        </div>

        <!-- Lost Payments Section -->
        @if(count($lostPayments) > 0)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="px-6 py-4 border-b border-red-200 dark:border-red-700">
                    <h3 class="text-lg font-medium text-red-800 dark:text-red-200 flex items-center">
                        <span class="mr-2">🚨</span>
                        الدفعات المفقودة المكتشفة
                    </h3>
                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                        هذه الدفعات نجحت في Stripe لكنها لا تزال معلقة في النظام
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-red-200 dark:divide-red-700">
                        <thead class="bg-red-100 dark:bg-red-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-800 dark:text-red-200 uppercase tracking-wider">
                                    Payment ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-800 dark:text-red-200 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-800 dark:text-red-200 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-800 dark:text-red-200 uppercase tracking-wider">
                                    Lost Since
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-800 dark:text-red-200 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-red-200 dark:divide-red-700">
                            @foreach($lostPayments as $lostPayment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        #{{ $lostPayment['payment']->id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $lostPayment['customer_email'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        ${{ number_format($lostPayment['amount'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $lostPayment['lost_duration'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button 
                                            wire:click="recoverSinglePayment({{ $lostPayment['payment']->id }})"
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">
                                            استعادة
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Recent Recoveries Section -->
        @if(count($recentRecoveries) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg border overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                        <span class="mr-2">✅</span>
                        الدفعات المستعادة مؤخراً
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Payment ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Method
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Recovered
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentRecoveries as $recovery)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        #{{ $recovery['id'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $recovery['customer_email'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        ${{ number_format($recovery['amount'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $recovery['method'] === 'Webhook' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $recovery['method'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $recovery['recovered_at'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Queue Health -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <span class="mr-2">🔄</span>
                صحة نظام المعالجة
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pending Jobs</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $healthStats['queue_health']['pending_jobs'] ?? 0 }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Failed Jobs</div>
                    <div class="text-xl font-bold {{ ($healthStats['queue_health']['failed_jobs'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ $healthStats['queue_health']['failed_jobs'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        كيفية الاستخدام
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <p>• اضغط "بحث عن الدفعات المفقودة" للبحث عن دفعات نجحت في Stripe لكن لم تكتمل في النظام</p>
                        <p>• استخدم "استعادة جميع المكتشفة" لاستعادة جميع الدفعات المفقودة دفعة واحدة</p>
                        <p>• يمكنك استعادة دفعة واحدة باستخدام زر "استعادة" بجانب كل دفعة</p>
                        <p>• النظام يعمل تلقائياً كل 30 دقيقة للبحث عن الدفعات المفقودة</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>