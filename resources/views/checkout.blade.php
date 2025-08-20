<!-- Debug: Promoted Payment Method = {{ $promotedPaymentMethod ?? 'null' }} -->
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
            background: linear-gradient(135deg, rgb(239 246 255) 0%, rgb(219 234 254) 100%) !important;
            box-shadow: 0 4px 20px -4px rgba(59, 130, 246, 0.3), 0 0 0 1px rgba(59, 130, 246, 0.1) !important;
            transform: translateY(-2px) scale(1.02) !important;
        }
        
        input[type="radio"]:checked + .payment-card-content .selection-indicator {
            border-color: rgb(59 130 246) !important;
            background-color: rgb(59 130 246) !important;
            box-shadow: 0 0 0 2px rgb(239 246 255), 0 2px 8px rgba(59, 130, 246, 0.3) !important;
        }
        
        input[type="radio"]:checked + .payment-card-content .selection-dot {
            opacity: 1 !important;
            background-color: white !important;
            transform: scale(1.2) !important;
        }
        
        /* Enhanced hover states for better interaction feedback */
        .payment-card-content:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15) !important;
            border-color: rgb(147 197 253) !important;
        }
        
        /* Override hover when selected */
        input[type="radio"]:checked + .payment-card-content:hover {
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 4px 25px -4px rgba(59, 130, 246, 0.4), 0 0 0 1px rgba(59, 130, 246, 0.2) !important;
            border-color: rgb(59 130 246) !important;
        }
        
        /* Smooth transitions for all interactive elements */
        .payment-card-content {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        .selection-indicator {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        .selection-dot {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* Promoted payment method styles */
        .promoted-payment {
            border-color: rgb(34 197 94) !important;
            background: linear-gradient(135deg, rgb(236 253 245) 0%, rgb(220 252 231) 100%) !important;
            position: relative;
            overflow: hidden;
        }

        .promoted-payment::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgb(34 197 94), rgb(16 185 129));
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }

        .promoted-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: linear-gradient(135deg, rgb(34 197 94), rgb(16 185 129));
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
            animation: glow 2s infinite alternate;
            z-index: 10;
            white-space: nowrap;
        }

        @keyframes pulse {
            0%, 100% { transform: rotate(10deg) scale(1); }
            50% { transform: rotate(10deg) scale(1.05); }
        }

        .free-month-banner {
            background: linear-gradient(135deg, rgb(16 185 129), rgb(34 197 94));
            color: white;
            padding: 8px;
            border-radius: 8px;
            text-align: center;
            margin-top: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            animation: glow 2s infinite alternate;
        }

        @keyframes glow {
            from { box-shadow: 0 2px 10px rgba(34, 197, 94, 0.3); }
            to { box-shadow: 0 2px 20px rgba(34, 197, 94, 0.6); }
        }
        
        /* Phone input styling fixes */
        .phone-input-container {
            min-height: 64px; /* Fixed height to prevent layout shifts */
        }
        
        .phone-prefix {
            min-width: 20px; /* Fixed width for + symbol */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .phone-input-field {
            min-height: 56px; /* Match the container height */
        }
        
        /* Ensure the phone input container maintains its shape */
        .phone-input-container:focus-within {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Error state styling for phone container */
        .phone-input-container.error {
            border-color: rgb(239 68 68) !important;
        }
        
        .phone-input-container.error:focus-within {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
            border-color: rgb(239 68 68) !important;
        }
        
        /* Success state styling for phone container */
        .phone-input-container.success {
            border-color: rgb(34 197 94) !important;
        }
        
        .phone-input-container.success:focus-within {
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1) !important;
            border-color: rgb(34 197 94) !important;
        }
    </style>
</head>
<body class="bg-white min-h-screen">
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
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
                                    <div id="email-error" class="mt-2 text-sm text-red-600 hidden"></div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-base font-semibold text-gray-700 mb-3">{{ __('checkout.phone_required') }}</label>
                                <div class="relative">
                                    <!-- Phone input with integrated prefix -->
                                    <div class="phone-input-container flex items-center border border-gray-300 rounded-xl shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all duration-200">
                                        <!-- Phone icon -->
                                        <div class="pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                        </div>
                                        <!-- Plus prefix (fixed) -->
                                        <div class="phone-prefix pl-3 pointer-events-none">
                                            <span class="text-gray-700 font-semibold text-base select-none">+</span>
                                        </div>
                                        <!-- Phone number input -->
                                        <input type="tel" id="phone" required
                                            class="phone-input-field flex-1 pl-2 pr-4 py-4 border-0 focus:outline-none focus:ring-0 text-base bg-transparent"
                                            placeholder="1 (555) 123-4567"
                                            pattern="[0-9\s\(\)\-]*"
                                            inputmode="numeric">
                                    </div>
                                    <div id="phone-error" class="mt-2 text-sm text-red-600 hidden"></div>
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
                                @if($promotedPaymentMethod === 'stripe')
                                    <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 z-20">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg animate-pulse whitespace-nowrap">
                                            ✨ {{ __('checkout.special_offer') }}
                                        </span>
                                    </div>
                                @endif
                                <input type="radio" name="payment_method" value="stripe" class="sr-only" {{ $promotedPaymentMethod === 'stripe' || empty($promotedPaymentMethod) ? 'checked' : '' }}>
                                <div class="payment-card-content flex flex-col items-center justify-between p-6 border-2 border-gray-200 rounded-2xl bg-white transition-all duration-200 hover:border-blue-300 hover:shadow-md h-full min-h-[200px] {{ $promotedPaymentMethod === 'stripe' ? 'promoted-payment' : '' }}">
                                    
                                    <!-- Radio Indicator -->
                                    <div class="absolute top-4 right-4 w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center selection-indicator">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full opacity-0 selection-dot"></div>
                                    </div>
                                    
                                    <div class="flex flex-col items-center w-full">
                                        <!-- Credit Card Icon -->
                                        <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mb-4">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                        </div>
                                        
                                        <!-- Card Title -->
                                        <h4 class="font-semibold text-gray-900 text-center mb-2">{{ __('checkout.credit_card') }}</h4>
                                        <p class="text-sm text-gray-500 text-center mb-4">{{ __('checkout.secure_payment') }}</p>
                                        
                                        <!-- Card Brand Icons -->
                                        <div class="flex items-center space-x-2 mb-2">
                                            <img src="https://iptvnordic.app/images/payment-icons/visa.svg" alt="Visa" class="h-6 w-auto">
                                            <img src="https://iptvnordic.app/images/payment-icons/mastercard.svg" alt="Mastercard" class="h-6 w-auto">
                                            <img src="https://iptvnordic.app/images/payment-icons/apple-pay.svg" alt="Apple Pay" class="h-6 w-auto">
                                            <img src="https://iptvnordic.app/images/payment-icons/google-pay.svg" alt="Google Pay" class="h-6 w-auto">
                                        </div>
                                    </div>

                                    @if($promotedPaymentMethod === 'stripe')
                                        <!-- Free Month Banner -->
                                        <div class="free-month-banner w-full mt-auto">
                                            <div class="text-xs">{{ __('checkout.stripe_special_offer') }}</div>
                                        </div>
                                    @endif
                                </div>
                            </label>
                            
                            <!-- PayPal Option -->
                            <label class="relative cursor-pointer">
                                @if($promotedPaymentMethod === 'paypal')
                                    <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 z-20">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg animate-pulse whitespace-nowrap">
                                            ✨ {{ __('checkout.special_offer') }}
                                        </span>
                                    </div>
                                @endif
                                <input type="radio" name="payment_method" value="paypal" class="sr-only" {{ $promotedPaymentMethod === 'paypal' ? 'checked' : '' }}>
                                <div class="payment-card-content flex flex-col items-center justify-between p-6 border-2 border-gray-200 rounded-2xl bg-white transition-all duration-200 hover:border-blue-300 hover:shadow-md h-full min-h-[200px] {{ $promotedPaymentMethod === 'paypal' ? 'promoted-payment' : '' }}">
                                    
                                    <!-- Radio Indicator -->
                                    <div class="absolute top-4 right-4 w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center selection-indicator">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full opacity-0 selection-dot"></div>
                                    </div>
                                    
                                    <div class="flex flex-col items-center w-full">
                                        <!-- PayPal Logo -->
                                        <div class="mb-4">
                                        </div>
                                        
                                        <!-- PayPal Title -->
                                        <h4 class="font-semibold text-gray-900 text-center mb-2">
                                            <img src="{{ asset('images/paypal-logo.svg') }}" alt="PayPal" class="h-6 w-auto mx-auto">
                                        </h4>
                                        <p class="text-sm text-gray-500 text-center mb-4">{{ __('checkout.secure_payment') }}</p>
                                        
                                        <!-- Digital wallet badges -->
                                        <div class="flex items-center space-x-2 mb-2">
                                            <div class="px-2 py-1 bg-gray-100 rounded text-xs text-gray-600 font-medium">Digital Wallet</div>
                                            <div class="px-2 py-1 bg-blue-50 rounded text-xs text-blue-600 font-medium">Instant</div>
                                        </div>
                                    </div>

                                    @if($promotedPaymentMethod === 'paypal')
                                        <!-- Free Month Banner -->
                                        <div class="free-month-banner w-full mt-auto">
                                            <div class="text-xs">{{ __('checkout.paypal_special_offer') }}</div>
                                        </div>
                                    @endif
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
                                <svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
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
            const phoneInput = document.getElementById('phone');
            const emailError = document.getElementById('email-error');
            const phoneError = document.getElementById('phone-error');
            
            // Enhanced email validation
            function validateEmail(email) {
                const re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
                return re.test(email) && email.length <= 254;
            }
            
            // Phone validation (international format)
            function validatePhone(phone) {
                // Phone input only contains digits (+ is outside the input)
                const cleanPhone = phone.replace(/[^\d]/g, '');
                
                // Must have 7-15 digits (+ is handled externally)
                const phoneRegex = /^[1-9]\d{6,14}$/;
                return phoneRegex.test(cleanPhone);
            }
            
            // Format phone number as user types (digits only)
            function formatPhone(phone) {
                // Remove all non-digit characters
                let cleaned = phone.replace(/[^\d]/g, '');
                
                // Don't allow leading zero
                if (cleaned.startsWith('0')) {
                    cleaned = cleaned.substring(1);
                }
                
                return cleaned;
            }
            
            // Get full phone number including the + prefix
            function getFullPhoneNumber(phoneInput) {
                const digits = phoneInput.replace(/[^\d]/g, '');
                return digits ? '+' + digits : '';
            }
            
            // Show error message
            function showError(input, errorElement, message) {
                if (input.id === 'phone') {
                    // For phone input, style the container
                    const container = input.closest('.phone-input-container');
                    if (container) {
                        container.classList.add('error');
                        container.classList.remove('success');
                    }
                } else {
                    // For email input, style the input directly
                    input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    input.classList.remove('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                }
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
            }
            
            // Hide error message
            function hideError(input, errorElement) {
                if (input.id === 'phone') {
                    // For phone input, style the container
                    const container = input.closest('.phone-input-container');
                    if (container) {
                        container.classList.remove('error');
                        container.classList.add('success');
                    }
                } else {
                    // For email input, style the input directly
                    input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    input.classList.add('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                }
                errorElement.classList.add('hidden');
            }
            
            // Reset input to neutral state
            function resetInputState(input) {
                if (input.id === 'phone') {
                    const container = input.closest('.phone-input-container');
                    if (container) {
                        container.classList.remove('error', 'success');
                    }
                } else {
                    input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    input.classList.add('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                }
            }
            
            // Real-time email validation
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                if (email) {
                    if (!validateEmail(email)) {
                        showError(this, emailError, 'Please enter a valid email address');
                    } else {
                        hideError(this, emailError);
                    }
                } else {
                    hideError(this, emailError);
                }
            });
            
            // Real-time phone validation and formatting
            phoneInput.addEventListener('input', function() {
                let phone = this.value;
                
                // Reset to neutral state when user starts typing
                if (phone.length <= 1) {
                    resetInputState(this);
                    const errorElement = document.getElementById('phone-error');
                    errorElement.classList.add('hidden');
                }
                
                // Format the phone number (digits only)
                const formatted = formatPhone(phone);
                if (formatted !== phone) {
                    const cursorPos = this.selectionStart;
                    this.value = formatted;
                    // Restore cursor position
                    this.setSelectionRange(cursorPos, cursorPos);
                }
                
                phone = this.value.trim();
                if (phone) {
                    if (!validatePhone(phone)) {
                        showError(this, phoneError, 'Please enter a valid phone number (e.g., 1234567890)');
                    } else {
                        hideError(this, phoneError);
                    }
                } else {
                    resetInputState(this);
                    phoneError.classList.add('hidden');
                }
            });
            
            // Prevent invalid characters in phone input (only digits allowed)
            phoneInput.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which);
                // Allow only digits and control keys
                if (!/[\d]/.test(char) && e.which !== 8 && e.which !== 0) {
                    e.preventDefault();
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
                
                // Validate payment method selection
                const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
                if (!selectedPaymentMethod) {
                    alert('{{ __('checkout.select_payment_method_error') }}');
                    // Scroll to payment method section
                    document.querySelector('input[name="payment_method"]').focus();
                    return;
                }
                
                // Validate email
                const email = emailInput.value.trim();
                if (!email) {
                    showError(emailInput, emailError, 'Email address is required');
                    emailInput.focus();
                    return;
                }
                
                if (!validateEmail(email)) {
                    showError(emailInput, emailError, 'Please enter a valid email address');
                    emailInput.focus();
                    return;
                }
                
                // Validate phone
                const phone = phoneInput.value.trim();
                if (!phone) {
                    showError(phoneInput, phoneError, 'Phone number is required');
                    phoneInput.focus();
                    return;
                }
                
                if (!validatePhone(phone)) {
                    showError(phoneInput, phoneError, 'Please enter a valid phone number (e.g., 1234567890)');
                    phoneInput.focus();
                    return;
                }
                
                // Add the + prefix to phone value before form submission
                const fullPhoneNumber = getFullPhoneNumber(phone);
                
                // Create a hidden input with the full phone number
                let hiddenPhoneInput = document.getElementById('phone-full');
                if (!hiddenPhoneInput) {
                    hiddenPhoneInput = document.createElement('input');
                    hiddenPhoneInput.type = 'hidden';
                    hiddenPhoneInput.id = 'phone-full';
                    hiddenPhoneInput.name = 'phone';
                    form.appendChild(hiddenPhoneInput);
                }
                hiddenPhoneInput.value = fullPhoneNumber;
                
                // Disable the visible phone input so it doesn't get submitted
                phoneInput.disabled = true;
                
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