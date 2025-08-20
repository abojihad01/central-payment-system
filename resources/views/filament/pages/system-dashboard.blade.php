<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Quick Stats Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                        <span>دفعات معلقة</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold {{ $healthStats['total_pending'] > 10 ? 'text-red-600' : 'text-green-600' }}">
                    {{ $healthStats['total_pending'] ?? 0 }}
                </div>
                <div class="text-sm text-gray-500">
                    منها {{ $healthStats['stuck_payments'] ?? 0 }} عالقة > 1 ساعة
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-blue-500" />
                        <span>معدل النجاح</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold {{ ($healthStats['success_rate_24h'] ?? 0) > 95 ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format($healthStats['success_rate_24h'] ?? 0, 1) }}%
                </div>
                <div class="text-sm text-gray-500">
                    آخر 24 ساعة ({{ ($healthStats['success_rate_1h'] ?? 0) }}% آخر ساعة)
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-arrow-path class="w-5 h-5 text-orange-500" />
                        <span>دفعات مفقودة</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold {{ count($lostPayments) > 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ count($lostPayments) }}
                </div>
                <div class="text-sm text-gray-500">
                    تحتاج استعادة فورية
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-queue-list class="w-5 h-5 text-purple-500" />
                        <span>مهام الطابور</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold {{ ($queueStats['failed_jobs'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ $queueStats['pending_jobs'] ?? 0 }}
                </div>
                <div class="text-sm text-gray-500">
                    معلقة ({{ $queueStats['failed_jobs'] ?? 0 }} فاشلة)
                </div>
            </x-filament::section>
        </div>

        {{-- Action Buttons Row --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-filament::button 
                wire:click="scanForLostPayments" 
                color="info" 
                icon="heroicon-o-magnifying-glass"
                :disabled="$isScanning"
                class="w-full">
                {{ $isScanning ? 'جاري البحث...' : 'بحث عن دفعات مفقودة' }}
            </x-filament::button>

            <x-filament::button 
                wire:click="recoverAllFoundPayments" 
                color="success" 
                icon="heroicon-o-arrow-path"
                class="w-full"
                :disabled="count($lostPayments) === 0">
                استعادة كل المكتشفة ({{ count($lostPayments) }})
            </x-filament::button>

            <x-filament::button 
                wire:click="runScheduler" 
                color="warning" 
                icon="heroicon-o-play"
                class="w-full">
                تشغيل المجدول الآن
            </x-filament::button>

            <x-filament::button 
                wire:click="clearFailedJobs" 
                color="danger" 
                icon="heroicon-o-trash"
                class="w-full"
                :disabled="($queueStats['failed_jobs'] ?? 0) === 0">
                مسح المهام الفاشلة
            </x-filament::button>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            {{-- Lost Payments Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                            <span>الدفعات المفقودة المكتشفة</span>
                        </div>
                        <x-filament::badge color="{{ count($lostPayments) > 0 ? 'danger' : 'success' }}">
                            {{ count($lostPayments) }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                @if(count($lostPayments) > 0)
                    <div class="space-y-3">
                        @foreach($lostPayments as $lostPayment)
                            <div class="border rounded-lg p-3 bg-red-50 border-red-200">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium">Payment #{{ $lostPayment['payment']->id }}</div>
                                        <div class="text-sm text-gray-600">{{ $lostPayment['customer_email'] }}</div>
                                        <div class="text-sm text-red-600">مفقودة منذ: {{ $lostPayment['lost_duration'] }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold">${{ number_format($lostPayment['amount'], 2) }}</div>
                                        <x-filament::badge color="success">مكتملة في Stripe</x-filament::badge>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 text-green-600">
                        <x-heroicon-o-check-circle class="w-8 h-8 mx-auto mb-2" />
                        <div>لا توجد دفعات مفقودة! النظام يعمل بشكل مثالي</div>
                    </div>
                @endif
            </x-filament::section>

            {{-- Recent Recoveries --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-green-500" />
                        <span>آخر الاستعادات</span>
                    </div>
                </x-slot>

                @if(count($recentRecoveries) > 0)
                    <div class="space-y-2">
                        @foreach(array_slice($recentRecoveries, 0, 5) as $recovery)
                            <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                                <div>
                                    <div class="font-medium">Payment #{{ $recovery['id'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $recovery['customer_email'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold">${{ number_format($recovery['amount'], 2) }}</div>
                                    <div class="text-xs text-gray-500">{{ $recovery['recovered_at'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-gray-500">
                        لا توجد استعادات حديثة
                    </div>
                @endif
            </x-filament::section>

            {{-- Scheduled Tasks Status --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <x-heroicon-o-clock class="w-5 h-5 text-blue-500" />
                            <span>حالة المهام المجدولة</span>
                        </div>
                        <x-filament::badge color="{{ $cronStatus['cron_service'] ? 'success' : 'danger' }}">
                            {{ $cronStatus['cron_service'] ? 'نشط' : 'معطل' }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>آخر تشغيل:</span>
                        <span class="font-medium">{{ $cronStatus['last_run'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>التشغيل التالي:</span>
                        <span class="font-medium">{{ $cronStatus['next_run'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>التكرار:</span>
                        <span class="font-medium">{{ $cronStatus['scheduler_frequency'] }}</span>
                    </div>
                </div>

                @if(count($scheduledTasks) > 0)
                    <div class="mt-4">
                        <h4 class="font-medium mb-2">المهام النشطة:</h4>
                        <div class="space-y-1">
                            @foreach(array_slice($scheduledTasks, 0, 3) as $task)
                                <div class="text-xs bg-gray-50 p-2 rounded">
                                    <div class="font-medium">{{ $task['description'] }}</div>
                                    <div class="text-gray-500">التالي: {{ $task['next_due'] }}</div>
                                </div>
                            @endforeach
                            @if(count($scheduledTasks) > 3)
                                <div class="text-xs text-gray-500 text-center">
                                    و {{ count($scheduledTasks) - 3 }} مهام أخرى...
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </x-filament::section>

            {{-- Queue Statistics --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <x-heroicon-o-queue-list class="w-5 h-5 text-purple-500" />
                            <span>إحصائيات الطابور</span>
                        </div>
                        <x-filament::badge color="{{ $queueStats['queue_workers_active'] ? 'success' : 'danger' }}">
                            {{ $queueStats['queue_workers_active'] ? 'نشط' : 'معطل' }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $queueStats['pending_jobs'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">مهام معلقة</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ $queueStats['processing_jobs'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">قيد التنفيذ</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">{{ $queueStats['failed_jobs'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">فاشلة</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $queueStats['total_jobs_today'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">اليوم</div>
                    </div>
                </div>
                
                <div class="mt-4 p-2 bg-gray-50 rounded">
                    <div class="flex justify-between text-sm">
                        <span>متوسط وقت التنفيذ:</span>
                        <span class="font-medium">{{ $queueStats['avg_processing_time'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- System Health Summary --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-heart class="w-5 h-5 text-pink-500" />
                    <span>ملخص صحة النظام</span>
                </div>
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div class="text-center">
                    <div class="text-lg font-bold">{{ $healthStats['total_payments_today'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">إجمالي الدفعات اليوم</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-green-600">{{ $healthStats['completed_payments_today'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">مكتملة اليوم</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold">{{ $healthStats['webhook_failures'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">فشل Webhooks</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold {{ $cronStatus['cron_service'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $cronStatus['cron_service'] ? '✓' : '✗' }}
                    </div>
                    <div class="text-xs text-gray-500">خدمة Cron</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold {{ $queueStats['queue_workers_active'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $queueStats['queue_workers_active'] ? '✓' : '✗' }}
                    </div>
                    <div class="text-xs text-gray-500">Queue Workers</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold">{{ $healthStats['last_recovery_run'] }}</div>
                    <div class="text-xs text-gray-500">آخر استعادة</div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>