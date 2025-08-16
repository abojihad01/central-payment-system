<!DOCTYPE html>
<html lang="{{ $language ?? 'ar' }}" dir="{{ $isRTL ?? true ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payment.payment_error_title') }} - {{ __('payment.central_payment_system') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
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
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <!-- Error Icon -->
            <div class="mb-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
            </div>

            <!-- Error Title -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ __('payment.payment_error_title') }}</h1>

            <!-- Error Message -->
            <p class="text-gray-600 mb-6 leading-relaxed">
                {{ $message }}
            </p>

            <!-- Technical Details (collapsed by default) -->
            <details class="mb-6 text-left">
                <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 font-medium">
                    {{ __('payment.technical_details') }}
                </summary>
                <div class="mt-3 p-3 bg-gray-50 rounded-md text-xs text-gray-600 font-mono">
                    {{ $error }}
                </div>
            </details>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button 
                    onclick="window.history.back()" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                    {{ __('payment.back_to_previous') }}
                </button>
                
                <button 
                    onclick="window.location.reload()" 
                    class="w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition-colors font-medium"
                >
                    {{ __('payment.try_again_button') }}
                </button>
            </div>

            <!-- Help Section -->
            <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-medium text-blue-900 mb-2">{{ __('payment.need_help_title') }}</h3>
                <p class="text-sm text-blue-700 mb-3">
                    {{ __('payment.need_help_description') }}
                </p>
                
                <!-- Quick Solutions -->
                <div class="text-xs text-blue-600 space-y-1 {{ $isRTL ?? true ? 'text-right' : 'text-left' }}">
                    <div>{{ __('payment.solution_expired') }}</div>
                    <div>{{ __('payment.solution_used_once') }}</div>
                    <div>{{ __('payment.solution_new_link') }}</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-gray-500">
            {{ __('payment.central_payment_system') }}
        </div>
    </div>

    <script>
        // تسجيل الخطأ للتحليل (اختياري)
        console.warn('Payment Error:', @json($error));
    </script>
</body>
</html>