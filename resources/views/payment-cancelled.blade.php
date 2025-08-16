<!DOCTYPE html>
<html lang="{{ $language ?? app()->getLocale() }}" dir="{{ $isRTL ?? (in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'])) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('payment.payment_cancelled') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .cancel-icon {
            animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        
        /* RTL Support */
        [dir="rtl"] .text-left { text-align: right !important; }
        [dir="rtl"] .text-right { text-align: left !important; }
        [dir="rtl"] .ml-2 { margin-left: 0; margin-right: 0.5rem; }
        [dir="rtl"] .mr-2 { margin-right: 0; margin-left: 0.5rem; }
        [dir="rtl"] .ml-3 { margin-left: 0; margin-right: 0.75rem; }
        [dir="rtl"] .mr-3 { margin-right: 0; margin-left: 0.75rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="glass-effect rounded-2xl shadow-xl p-8 text-center animate-fade-in">
            <!-- Cancel Icon -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6 cancel-icon">
                <svg class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                {{ __('payment.payment_cancelled') }}
            </h1>

            <!-- Message -->
            <p class="text-gray-600 mb-6 leading-relaxed">
                {{ __('payment.payment_cancelled_description') }}
            </p>

            <!-- Cancellation Details -->
            @if(request('token'))
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-left">
                <div class="font-medium text-gray-700 mb-2">{{ __('payment.transaction_details') }}</div>
                <div class="text-gray-600">
                    <span class="font-medium">{{ __('payment.order_id') }}</span> {{ request('token') }}
                </div>
                <div class="text-gray-600">
                    <span class="font-medium">{{ __('payment.status') }}</span> {{ __('payment.cancelled') }}
                </div>
                <div class="text-gray-600">
                    <span class="font-medium">{{ __('payment.date') }}</span> {{ now()->format('M j, Y g:i A') }}
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="space-y-3">
                @if(request('retry_url'))
                <a href="{{ request('retry_url') }}" 
                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition duration-200 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ __('payment.try_again') }}
                </a>
                @endif
                
                <a href="/" 
                   class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-xl transition duration-200 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('payment.return_home') }}
                </a>
            </div>

            <!-- Help Text -->
            <div class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-500">
                {{ __('payment.support_message') }}
            </div>
        </div>
    </div>
</body>
</html>