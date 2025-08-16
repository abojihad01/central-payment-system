<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-lg {{ $config['configured'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Google reCAPTCHA Configuration
                        </h3>
                        <p class="text-sm text-gray-500">
                            Status: <span class="font-medium {{ $config['configured'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $config['configured'] ? 'CONFIGURED' : 'NOT CONFIGURED' }}
                            </span>
                        </p>
                    </div>
                </div>
                
                @if($config['configured'])
                    <button 
                        wire:click="testRecaptcha"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700">
                        Test Connection
                    </button>
                @endif
            </div>

            @if(!$config['configured'])
                <!-- Configuration Instructions -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Setup Required</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p class="mb-2">To enable reCAPTCHA protection, follow these steps:</p>
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Visit <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="text-blue-600 hover:underline font-medium">Google reCAPTCHA Admin Console</a></li>
                                    <li>Create a new site with <strong>reCAPTCHA v3</strong></li>
                                    <li>Add your domain(s): <code class="bg-yellow-100 px-1 rounded">{{ request()->getHost() }}</code></li>
                                    <li>Copy the keys to your environment configuration:</li>
                                </ol>
                                <div class="bg-gray-900 text-gray-100 rounded mt-3 p-3 font-mono text-sm">
                                    <div class="text-green-400"># Add to your .env file</div>
                                    <div>RECAPTCHA_SITE_KEY=your_site_key_here</div>
                                    <div>RECAPTCHA_SECRET_KEY=your_secret_key_here</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Configuration Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Current Configuration -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-900">Current Configuration</h4>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Site Key</span>
                                <code class="text-xs bg-white px-2 py-1 rounded border">
                                    {{ $siteKey ? substr($siteKey, 0, 20) . '...' : 'Not set' }}
                                </code>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Secret Key</span>
                                <code class="text-xs bg-white px-2 py-1 rounded border">
                                    {{ $secretKey ? '••••••••••••••••••••' : 'Not set' }}
                                </code>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Version</span>
                                <span class="text-sm font-medium text-blue-600">{{ strtoupper($settings['version']) }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Status</span>
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded {{ $settings['enabled'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $settings['enabled'] ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Settings Control -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-900">Settings</h4>
                        
                        <div class="space-y-4">
                            <!-- Threshold Control -->
                            <div class="p-4 border border-gray-200 rounded-lg">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Score Threshold: {{ $settings['threshold'] }}
                                </label>
                                <input 
                                    type="range" 
                                    min="0" 
                                    max="1" 
                                    step="0.1" 
                                    value="{{ $settings['threshold'] }}"
                                    wire:change="updateThreshold($event.target.value)"
                                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>0.0 (Lenient)</span>
                                    <span>0.5 (Balanced)</span>
                                    <span>1.0 (Strict)</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-2">
                                    Higher values are more strict. 0.5 is recommended for most sites.
                                </p>
                            </div>
                            
                            <!-- Protected Actions -->
                            <div class="p-4 border border-gray-200 rounded-lg">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Protected Actions</h5>
                                <div class="space-y-2">
                                    @foreach($settings['actions'] as $action)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                            {{ ucfirst($action) }}
                                        </span>
                                    @endforeach
                                </div>
                                <p class="text-xs text-gray-600 mt-2">
                                    These actions require reCAPTCHA verification when enabled.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usage Example -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">Usage in Blade Templates</h4>
                    <div class="bg-blue-900 text-blue-100 rounded p-3 font-mono text-sm">
                        <div class="text-blue-300">{{-- reCAPTCHA v3 (Invisible) --}}</div>
                        <div>&lt;x-recaptcha action="payment" threshold="0.7" version="v3" /&gt;</div>
                        <br>
                        <div class="text-blue-300">{{-- reCAPTCHA v2 (Checkbox) --}}</div>
                        <div>&lt;x-recaptcha version="v2" /&gt;</div>
                    </div>
                </div>
            @endif
            
            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                <a href="{{ route('filament.admin.resources.bot-protection-settings.index') }}?activeTab=recaptcha" 
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                    Configure Settings
                </a>
                
                <a href="https://www.google.com/recaptcha/admin" target="_blank"
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.56-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.56.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd"/>
                    </svg>
                    Google reCAPTCHA Console
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>