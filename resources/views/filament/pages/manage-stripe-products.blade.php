<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Stripe Accounts Overview -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Stripe Accounts</h3>
            
            @if(count($this->stripeAccounts) === 0)
                <div class="text-center py-8">
                    <div class="text-gray-400 text-sm">No Stripe accounts configured</div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($this->stripeAccounts as $account)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900">{{ $account['name'] }}</h4>
                                <span class="px-2 py-1 text-xs rounded-full {{ $account['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($account['status']) }}
                                </span>
                            </div>
                            <div class="space-y-1 text-sm text-gray-600">
                                <div>Credentials: {{ $account['has_credentials'] ? '✓ Configured' : '✗ Missing' }}</div>
                                <div>Transactions: {{ $account['total_transactions'] }}</div>
                                <div>Success Rate: {{ $account['success_rate'] }}%</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Plans and Stripe Products -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Plans & Stripe Products</h3>
                <p class="text-sm text-gray-600 mt-1">Manage Stripe products and prices for your subscription plans</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stripe Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Links</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($this->plans as $plan)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $plan['name'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">${{ number_format($plan['price'], 2) }} {{ strtoupper($plan['currency']) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $plan['duration_days'] ? $plan['duration_days'] . ' days' : 'Lifetime' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $plan['recurring'] ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $plan['recurring'] ? 'Recurring' : 'One-time' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $plan['is_synced'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $plan['is_synced'] ? 'Synced' : 'Not Synced' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($plan['stripe_product_id'])
                                        <div class="text-xs text-gray-500 font-mono">
                                            {{ $plan['stripe_product_id'] }}
                                        </div>
                                        <div class="text-xs text-gray-400 font-mono">
                                            {{ $plan['stripe_price_id'] }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400">Not created</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $plan['links_count'] }} links</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="text-sm font-medium text-blue-900 mb-2">How to use Stripe Subscriptions:</h4>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>1. Mark plans as "Recurring" to enable Stripe subscriptions</li>
                <li>2. Create Stripe products/prices using the "Create Stripe Product" button</li>
                <li>3. Existing payment links will automatically use subscription mode for recurring plans</li>
                <li>4. Webhooks will handle recurring billing and subscription management</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>