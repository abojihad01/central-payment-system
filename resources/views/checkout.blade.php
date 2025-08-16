<!DOCTYPE html>
<html lang="{{ $language ?? app()->getLocale() }}" dir="{{ $isRTL ?? (in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'])) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('checkout.title') }} - {{ $paymentData['website_name'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #ffffff;
        }
        .glass-effect {
            background: rgba(59, 130, 246, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .payment-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px -8px rgba(102, 126, 234, 0.6);
        }
        .feature-item {
            opacity: 0;
            animation: slideInUp 0.6s ease-out forwards;
        }
        .feature-item:nth-child(1) { animation-delay: 0.1s; }
        .feature-item:nth-child(2) { animation-delay: 0.2s; }
        .feature-item:nth-child(3) { animation-delay: 0.3s; }
        .feature-item:nth-child(4) { animation-delay: 0.4s; }
        .feature-item:nth-child(5) { animation-delay: 0.5s; }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .max-w-lg { max-width: 100%; }
            .px-8 { padding-left: 1rem; padding-right: 1rem; }
            .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
            .text-4xl { font-size: 2rem; line-height: 2.5rem; }
            .enhanced-header { flex-direction: column; }
            .progress-steps { flex-direction: column; gap: 1rem; }
            .progress-line { width: 2px; height: 1rem; }
            .header-content { flex-direction: column; align-items: center; text-align: center; }
            .trust-badges { align-items: center; text-align: center; }
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* RTL Support */
        [dir="rtl"] .text-left { text-align: right !important; }
        [dir="rtl"] .text-right { text-align: left !important; }
        [dir="rtl"] .ml-2 { margin-left: 0; margin-right: 0.5rem; }
        [dir="rtl"] .mr-2 { margin-right: 0; margin-left: 0.5rem; }
        [dir="rtl"] .ml-3 { margin-left: 0; margin-right: 0.75rem; }
        [dir="rtl"] .mr-3 { margin-right: 0; margin-left: 0.75rem; }
        [dir="rtl"] .ml-4 { margin-left: 0; margin-right: 1rem; }
        [dir="rtl"] .mr-4 { margin-right: 0; margin-left: 1rem; }
        [dir="rtl"] .pl-3 { padding-left: 0; padding-right: 0.75rem; }
        [dir="rtl"] .pr-3 { padding-right: 0; padding-left: 0.75rem; }
        [dir="rtl"] .pl-4 { padding-left: 0; padding-right: 1rem; }
        [dir="rtl"] .pr-4 { padding-right: 0; padding-left: 1rem; }
        
        /* Payment method selection */
        .payment-method-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.12);
        }
        
        /* Selection states */
        input[type="radio"]:checked + .payment-card-content {
            border-color: rgb(59 130 246) !important;
            background-color: rgb(239 246 255) !important;
        }
        
        input[type="radio"]:checked + .payment-card-content .selection-indicator {
            border-color: rgb(59 130 246) !important;
        }
        
        input[type="radio"]:checked + .payment-card-content .selection-dot {
            opacity: 1 !important;
        }
    </style>
</head>
<body class="bg-white min-h-screen">
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="text-white mt-4 font-medium">{{ __('checkout.processing') }}</p>
        </div>
    </div>

    <!-- Background Pattern -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-gray-100 opacity-30 rounded-full"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-50 opacity-40 rounded-full"></div>
    </div>
    
    <div class="relative min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-lg w-full space-y-8 animate-fade-in">
            

            <!-- Plan Details Card -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden payment-card">
                <!-- Plan Header -->
                <div class="px-8 py-8 bg-gradient-to-r from-purple-600 to-blue-600 text-white border-b border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-white mb-2">{{ $paymentData['plan_name'] }}</h3>
                            <p class="text-purple-100">{{ $paymentData['plan_description'] }}</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-baseline justify-end gap-2 mb-2">
                                <div class="text-sm font-medium text-purple-200 uppercase tracking-wide">
                                    {{ strtoupper($paymentData['currency']) }}
                                </div>
                                <div class="text-4xl font-bold text-white">
                                    {{ number_format($paymentData['price'], 2) }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/20 text-white backdrop-blur-sm">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('checkout.secure_payment') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden payment-card">
                <form id="payment-form" method="POST" action="{{ route('process-payment') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    
                    <!-- Form Header -->
                    <div class="px-8 py-8 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Customer Information
                            </h3>
                            
                            <!-- Website Logo -->
                            @if(!empty($paymentData['website_logo']))
                                <div class="inline-flex items-center justify-center w-12 h-12 bg-white rounded-full shadow-md border border-gray-200 overflow-hidden">
                                    <img src="{{ $paymentData['website_logo'] }}" 
                                         alt="{{ $paymentData['website_name'] }} Logo" 
                                         class="w-full h-full object-contain p-1"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="hidden w-full h-full items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600 rounded-full">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                </div>
                            @else
                                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full shadow-md">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <div class="px-8 py-8 space-y-8">
                        <div class="grid grid-cols-1 gap-8">
                            <div>
                                <label for="email" class="block text-base font-semibold text-gray-700 mb-3">{{ __('checkout.email_required') }}</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                        </svg>
                                    </div>
                                    <input type="email" id="email" name="email" required
                                        class="w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-base"
                                        placeholder="your@email.com">
                                </div>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-base font-semibold text-gray-700 mb-3">{{ __('checkout.phone_optional') }}</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                    </div>
                                    <input type="tel" id="phone" name="phone"
                                        class="w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-base"
                                        placeholder="+1 (555) 123-4567">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Selection -->
                    <div class="px-8 py-8 border-t border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                            {{ __('checkout.payment_method') }}
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Stripe Option -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="payment_method" value="stripe" class="sr-only" checked>
                                <div class="payment-card-content flex flex-col items-center justify-center p-6 border-2 border-gray-200 rounded-2xl bg-white transition-all duration-200 hover:border-blue-300 hover:shadow-md">
                                    <!-- Radio Indicator -->
                                    <div class="absolute top-4 right-4 w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center selection-indicator">
                                        <div class="w-2.5 h-2.5 bg-blue-500 rounded-full opacity-0 selection-dot"></div>
                                    </div>
                                    
                                    <!-- Credit Card Icon -->
                                    <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                        </svg>
                                    </div>
                                    
                                    <!-- Card Title -->
                                    <h4 class="font-semibold text-gray-900 text-center mb-2">{{ __('checkout.credit_card') }}</h4>
                                    <p class="text-sm text-gray-500 text-center mb-4">Secure payment</p>
                                    
                                    <!-- Card Brand Icons -->
                                    <div class="flex items-center space-x-1">
                                        <div class="w-8 h-5 bg-blue-600 rounded text-xs text-white font-bold flex items-center justify-center">VISA</div>
                                        <div class="w-8 h-5 bg-red-600 rounded text-xs text-white font-bold flex items-center justify-center">MC</div>
                                        <div class="w-8 h-5 bg-yellow-500 rounded text-xs text-white font-bold flex items-center justify-center">AE</div>
                                    </div>
                                </div>
                            </label>
                            
                            <!-- PayPal Option -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="payment_method" value="paypal" class="sr-only">
                                <div class="payment-card-content flex flex-col items-center justify-center p-6 border-2 border-gray-200 rounded-2xl bg-white transition-all duration-200 hover:border-blue-300 hover:shadow-md">
                                    <!-- Radio Indicator -->
                                    <div class="absolute top-4 right-4 w-5 h-5 border-2 border-gray-300 rounded-full flex items-center justify-center selection-indicator">
                                        <div class="w-2.5 h-2.5 bg-blue-500 rounded-full opacity-0 selection-dot"></div>
                                    </div>
                                    
                                    <!-- PayPal Icon -->
                                    <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm1.262-8.13a.858.858 0 0 1 .854-.693h2.19c3.517 0 6.063-1.43 6.898-5.524.027-.134.049-.27.067-.405.54-4.054-1.617-6.116-6.177-6.116H5.998L4.18 12.537l.002-.002c.114-.688.687-1.195 1.397-1.195l2.759-.033z"/>
                                        </svg>
                                    </div>
                                    
                                    <!-- PayPal Title -->
                                    <h4 class="font-semibold text-gray-900 text-center mb-2">{{ __('checkout.paypal') }}</h4>
                                    <p class="text-sm text-gray-500 text-center mb-4">PayPal account</p>
                                    
                                    <!-- PayPal Logo -->
                                    <div class="text-blue-600 font-bold text-lg">PayPal</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div id="stripe-section" class="px-8 py-6">
                        <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-xl p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-full">
                                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="font-semibold text-gray-800">{{ __('checkout.secure_payment') }}</p>
                                    <p class="text-sm text-gray-600 mt-1">{{ __('checkout.redirect_notice') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PayPal Section -->
                    <div id="paypal-section" class="px-8 py-6 hidden">
                        <div id="paypal-button-container"></div>
                    </div>

                    <!-- Submit Button -->
                    <div class="px-8 py-8">
                        <button type="submit" id="submit-button" 
                            class="w-full btn-primary text-white py-5 px-6 rounded-2xl font-semibold text-lg shadow-lg focus:outline-none focus:ring-4 focus:ring-purple-300 relative overflow-hidden group">
                            <span id="button-text" class="flex items-center justify-center">
                                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                {{ __('checkout.pay_button', ['amount' => number_format($paymentData['price'], 2), 'currency' => strtoupper($paymentData['currency'])]) }}
                            </span>
                            <span id="spinner" class="hidden flex items-center justify-center">
                                <svg class="animate-spin h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('checkout.processing') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Notice -->
            <div class="text-center animate-fade-in mt-8">
                <div class="glass-effect rounded-xl p-6 mx-4">
                    <p class="text-base text-gray-700 font-medium flex items-center justify-center mb-4">
                        <svg class="h-6 w-6 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        {{ __('checkout.security_notice') }}
                    </p>
                    <div class="flex items-center justify-center space-x-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            256-bit SSL
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            PCI Compliant
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            GDPR Protected
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const form = document.getElementById('payment-form');
            const submitButton = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            const spinner = document.getElementById('spinner');
            const loadingOverlay = document.getElementById('loading-overlay');
            const emailInput = document.getElementById('email');
            
            // Email validation
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            // Real-time email validation
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                if (email && !validateEmail(email)) {
                    this.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    this.classList.remove('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                } else {
                    this.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    this.classList.add('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                }
            });

            // Handle payment method selection
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const stripeSection = document.getElementById('stripe-section');
                    const paypalSection = document.getElementById('paypal-section');
                    const buttonText = submitButton.querySelector('#button-text');
                    
                    // Add smooth transition
                    stripeSection.style.transition = 'all 0.3s ease';
                    paypalSection.style.transition = 'all 0.3s ease';

                    if (this.value === 'stripe') {
                        stripeSection.classList.remove('hidden');
                        paypalSection.classList.add('hidden');
                        submitButton.classList.remove('hidden');
                        buttonText.innerHTML = `
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            {{ __('checkout.pay_button', ['amount' => number_format($paymentData['price'], 2), 'currency' => strtoupper($paymentData['currency'])]) }}
                        `;
                    } else if (this.value === 'paypal') {
                        stripeSection.classList.add('hidden');
                        paypalSection.classList.remove('hidden');
                        submitButton.classList.remove('hidden');
                        buttonText.innerHTML = `
                            <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm1.262-8.13a.858.858 0 0 1 .854-.693h2.19c3.517 0 6.063-1.43 6.898-5.524.027-.134.049-.27.067-.405.54-4.054-1.617-6.116-6.177-6.116H5.998L4.18 12.537l.002-.002c.114-.688.687-1.195 1.397-1.195l2.759-.033z"/>
                            </svg>
                            Pay with PayPal
                        `;
                    }
                });
            });

            // Handle form submission
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                
                // Validate email
                const email = emailInput.value.trim();
                if (!email) {
                    emailInput.focus();
                    emailInput.classList.add('border-red-500');
                    return;
                }
                
                if (!validateEmail(email)) {
                    emailInput.focus();
                    emailInput.classList.add('border-red-500');
                    return;
                }
                
                // Show loading states
                setLoading(true);
                showLoadingOverlay(true);
                
                // Submit form after a small delay for better UX
                setTimeout(() => {
                    form.submit();
                }, 500);
            });

            function setLoading(isLoading) {
                if (isLoading) {
                    buttonText.classList.add('hidden');
                    spinner.classList.remove('hidden');
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-80');
                } else {
                    buttonText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-80');
                }
            }
            
            function showLoadingOverlay(show) {
                if (show) {
                    loadingOverlay.style.display = 'flex';
                } else {
                    loadingOverlay.style.display = 'none';
                }
            }
            
            // Add subtle hover effects to form inputs
            const inputs = document.querySelectorAll('input[type="email"], input[type="tel"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('transform', 'scale-[1.02]');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('transform', 'scale-[1.02]');
                });
            });
        });
    </script>
</body>
</html>