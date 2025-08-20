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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sync Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accounts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stripe Products</th>
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
                                    <span class="px-2 py-1 text-xs rounded-full {{ $plan['fully_synced'] ? 'bg-green-100 text-green-800' : ($plan['accounts_synced'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        @if($plan['fully_synced'])
                                            ✓ Fully Synced
                                        @elseif($plan['accounts_synced'] > 0)
                                            ⚠ Partial Sync
                                        @else
                                            ✗ Not Synced
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $plan['accounts_synced'] }} / {{ $plan['total_accounts'] }} accounts
                                    </div>
                                    @if(count($plan['sync_status']) > 0)
                                        <div class="text-xs text-gray-500 mt-1">
                                            @foreach($plan['sync_status'] as $accountId => $status)
                                                <div class="flex items-center gap-1">
                                                    <span class="{{ $status['synced'] ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $status['synced'] ? '✓' : '✗' }}
                                                    </span>
                                                    <span>{{ $status['name'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="max-w-xs">
                                        @if($plan['stripe_product_id'] || !empty($plan['sync_status']))
                                            {{-- Show count of products --}}
                                            <div class="text-xs text-gray-500 mb-2">
                                                @if($plan['accounts_synced'] > 0)
                                                    {{ $plan['accounts_synced'] }} product(s) in Stripe
                                                @else
                                                    No Stripe products
                                                @endif
                                            </div>
                                            
                                            {{-- Expandable details --}}
                                            <details class="group">
                                                <summary class="cursor-pointer text-xs text-blue-600 hover:text-blue-800 select-none">
                                                    View Product IDs →
                                                </summary>
                                                <div class="mt-2 space-y-1 pl-2 border-l-2 border-blue-100">
                                                    @if($plan['stripe_product_id'])
                                                        {{-- Legacy/Primary product --}}
                                                        <div class="p-2 bg-gray-50 rounded text-xs">
                                                            <div class="font-semibold text-gray-600">Primary Account:</div>
                                                            <div class="text-gray-500 font-mono break-all">{{ $plan['stripe_product_id'] }}</div>
                                                            <div class="text-gray-400 font-mono break-all">{{ $plan['stripe_price_id'] }}</div>
                                                        </div>
                                                    @endif
                                                    
                                                    @if(!empty($plan['sync_status']))
                                                        {{-- All accounts products --}}
                                                        @foreach($plan['sync_status'] as $accountId => $status)
                                                            @if($status['synced'])
                                                                <div class="p-2 bg-blue-50 rounded text-xs">
                                                                    <div class="font-semibold text-blue-600">{{ $status['name'] }}:</div>
                                                                    <div class="text-blue-500 font-mono break-all">{{ $status['product_id'] }}</div>
                                                                    <div class="text-blue-400 font-mono break-all">{{ $status['price_id'] }}</div>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </details>
                                        @else
                                            <div class="text-xs text-gray-400">Not created</div>
                                        @endif
                                    </div>
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
            <h4 class="text-sm font-medium text-blue-900 mb-2">How to manage Stripe Products:</h4>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>1. <strong>Sync Plans Across All Accounts:</strong> Ensures all plans are available in all Stripe accounts for seamless payment processing</li>
                <li>2. <strong>Create Stripe Product:</strong> Create products/prices for individual plans in specific accounts</li>
                <li>3. <strong>Import from Stripe:</strong> Import existing products from your Stripe account</li>
                <li>4. <strong>Sync Status:</strong> Monitor which accounts have each plan synced</li>
                <li>5. Recurring plans automatically enable Stripe subscription mode</li>
            </ul>
        </div>
        
        <!-- Sync Status Legend -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-900 mb-2">Sync Status Legend:</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">✓ Fully Synced</span>
                    <span class="text-gray-600">Available in all Stripe accounts</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">⚠ Partial Sync</span>
                    <span class="text-gray-600">Available in some accounts only</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">✗ Not Synced</span>
                    <span class="text-gray-600">Not available in any Stripe account</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>