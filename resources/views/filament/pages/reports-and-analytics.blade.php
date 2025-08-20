<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Filters Section --}}
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            {{ $this->form }}
        </div>

        {{-- Overview Statistics --}}
        @php
            $stats = $this->getOverviewStats();
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-green-500" />
                        <span>إجمالي الإيرادات</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold text-green-600">
                    ${{ number_format($stats['total_revenue'], 2) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-blue-500" />
                        <span>إجمالي المدفوعات</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold text-blue-600">
                    {{ number_format($stats['total_payments']) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-calculator class="w-5 h-5 text-purple-500" />
                        <span>متوسط قيمة الدفعة</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold text-purple-600">
                    ${{ number_format($stats['average_payment'], 2) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-yellow-500" />
                        <span>مدفوعات معلقة</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold text-yellow-600">
                    {{ number_format($stats['pending_payments']) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-500" />
                        <span>مدفوعات فاشلة</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold text-red-600">
                    {{ number_format($stats['failed_payments']) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <x-slot name="heading">
                    <div class="flex items-center justify-center space-x-2">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-green-500" />
                        <span>معدل النجاح</span>
                    </div>
                </x-slot>
                <div class="text-2xl font-bold {{ $stats['success_rate'] > 80 ? 'text-green-600' : ($stats['success_rate'] > 60 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $stats['success_rate'] }}%
                </div>
            </x-filament::section>
        </div>

        {{-- Main Table: Revenue by Website --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-blue-500" />
                    <span>الإيرادات حسب الموقع</span>
                </div>
            </x-slot>
            
            {{ $this->table }}
        </x-filament::section>

        {{-- Top Performing Generated Links --}}
        @php
            $topLinks = $this->getTopGeneratedLinks();
        @endphp
        
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-link class="w-5 h-5 text-green-500" />
                    <span>أفضل الروابط المُولدة أداءً (أعلى 10)</span>
                </div>
            </x-slot>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                رمز الرابط
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                الموقع
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                السعر المحدد
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                إجمالي الإيرادات
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                المدفوعات الناجحة
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                إجمالي المدفوعات
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                معدل النجاح
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                تاريخ الإنشاء
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($topLinks as $link)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ Str::limit($link['token'], 8) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $link['website_name'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ number_format($link['price'], 2) }} {{ $link['currency'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                ${{ number_format($link['total_revenue'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $link['successful_payments'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $link['total_payments'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @php
                                    $successRate = $link['total_payments'] > 0 ? round(($link['successful_payments'] / $link['total_payments']) * 100, 1) : 0;
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $successRate > 80 ? 'bg-green-100 text-green-800' : ($successRate > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $successRate }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($link['created_at'])->format('Y-m-d') }}
                            </td>
                        </tr>
                        @endforeach
                        
                        @if(empty($topLinks))
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                لا توجد بيانات متاحة للفترة المحددة
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Monthly Revenue Trend --}}
        @php
            $monthlyRevenue = $this->getRevenueByMonth();
        @endphp
        
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-purple-500" />
                    <span>اتجاه الإيرادات الشهرية (آخر 12 شهر)</span>
                </div>
            </x-slot>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                الشهر
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                الإيرادات
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                عدد المدفوعات
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                متوسط قيمة الدفعة
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($monthlyRevenue as $month)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $month['month'])->format('Y/m') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                ${{ number_format($month['revenue'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ number_format($month['payments_count']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ number_format($month['revenue'] / max($month['payments_count'], 1), 2) }}
                            </td>
                        </tr>
                        @endforeach
                        
                        @if(empty($monthlyRevenue))
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                لا توجد بيانات شهرية متاحة
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>

    <script>
        // Auto-refresh the page data when filters change
        window.addEventListener('DOMContentLoaded', function() {
            const filterElements = document.querySelectorAll('[wire\\:model]');
            filterElements.forEach(element => {
                element.addEventListener('change', function() {
                    // Trigger a small delay to allow Livewire to update
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                });
            });
        });
    </script>
</x-filament-panels::page>