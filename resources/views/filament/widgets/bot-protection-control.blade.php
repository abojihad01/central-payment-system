<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- Header with Master Toggle -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-lg {{ $enabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <x-heroicon-s-shield-check class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            مركز التحكم بالحماية من البوتات
                        </h3>
                        <p class="text-sm text-gray-500">
                            الحالة: <span class="font-medium {{ $enabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $enabled ? 'مفعل' : 'معطل' }}
                            </span>
                        </p>
                    </div>
                </div>
                
                <button 
                    wire:click="toggleProtection"
                    wire:confirm="{{ $enabled ? 'سيؤدي هذا لإيقاف الحماية من البوتات وجعل موقعك عرضة للهجمات. هل أنت متأكد؟' : 'سيؤدي هذا لتفعيل الحماية من البوتات بالإعدادات الحالية.' }}"
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors {{ $enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }}">
                    {{ $enabled ? 'إيقاف الحماية' : 'تفعيل الحماية' }}
                </button>
            </div>

            <!-- Protection Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Rate Limiting -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">تحديد المعدل</h4>
                        <button 
                            wire:click="toggleFeature('rate_limit_enabled')"
                            class="w-6 h-6 rounded {{ $settings['rate_limit_enabled'] ? 'bg-green-500' : 'bg-gray-300' }} relative inline-flex items-center transition-colors">
                            <span class="w-2 h-2 bg-white rounded-full transform transition-transform {{ $settings['rate_limit_enabled'] ? 'translate-x-3' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">
                        الحد الأقصى: {{ $settings['rate_limit_requests'] }} طلب/دقيقة
                    </p>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $settings['rate_limit_enabled'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $settings['rate_limit_enabled'] ? 'نشط' : 'غير نشط' }}
                        </span>
                    </div>
                </div>

                <!-- Bot Detection -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">كشف البوتات</h4>
                        <button 
                            wire:click="toggleFeature('bot_detection_enabled')"
                            class="w-6 h-6 rounded {{ $settings['bot_detection_enabled'] ? 'bg-green-500' : 'bg-gray-300' }} relative inline-flex items-center transition-colors">
                            <span class="w-2 h-2 bg-white rounded-full transform transition-transform {{ $settings['bot_detection_enabled'] ? 'translate-x-3' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">
                        User agent analysis
                    </p>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $settings['bot_detection_enabled'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $settings['bot_detection_enabled'] ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <!-- Honeypot Protection -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">Honeypot</h4>
                        <button 
                            wire:click="toggleFeature('honeypot_enabled')"
                            class="w-6 h-6 rounded {{ $settings['honeypot_enabled'] ? 'bg-green-500' : 'bg-gray-300' }} relative inline-flex items-center transition-colors">
                            <span class="w-2 h-2 bg-white rounded-full transform transition-transform {{ $settings['honeypot_enabled'] ? 'translate-x-3' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">
                        Min time: {{ $settings['min_form_time'] }}s
                    </p>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $settings['honeypot_enabled'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $settings['honeypot_enabled'] ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <!-- reCAPTCHA -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">reCAPTCHA</h4>
                        <button 
                            wire:click="toggleFeature('recaptcha_enabled')"
                            class="w-6 h-6 rounded {{ $settings['recaptcha_enabled'] ? 'bg-green-500' : 'bg-gray-300' }} relative inline-flex items-center transition-colors">
                            <span class="w-2 h-2 bg-white rounded-full transform transition-transform {{ $settings['recaptcha_enabled'] ? 'translate-x-3' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">
                        Human verification
                    </p>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $settings['recaptcha_enabled'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $settings['recaptcha_enabled'] ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-s-exclamation-triangle class="w-8 h-8 text-red-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-900">Total Detections</p>
                            <p class="text-2xl font-bold text-red-600">{{ $stats['total_detections'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-s-shield-exclamation class="w-8 h-8 text-yellow-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-900">Blocked</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ $stats['blocked_count'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-s-globe-alt class="w-8 h-8 text-blue-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-900">Unique IPs</p>
                            <p class="text-2xl font-bold text-blue-600">{{ $stats['unique_ips'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-s-chart-bar class="w-8 h-8 text-purple-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-purple-900">Top Attack</p>
                            <p class="text-sm font-bold text-purple-600">
                                @if(!empty($stats['by_type']))
                                    {{ ucfirst(array_key_first($stats['by_type'])) }}
                                @else
                                    None
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.resources.bot-detections.index') }}" 
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                    <x-heroicon-s-eye class="w-4 h-4 mr-2" />
                    View Detections
                </a>
                
                <a href="{{ route('filament.admin.resources.bot-protection-settings.index') }}" 
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    <x-heroicon-s-cog-6-tooth class="w-4 h-4 mr-2" />
                    Configure Settings
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>