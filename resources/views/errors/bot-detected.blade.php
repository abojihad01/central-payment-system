<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وصول مرفوض - Bot Detected</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8 text-center">
            <!-- Icon -->
            <div class="mb-6">
                <svg class="mx-auto h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                وصول مرفوض
            </h1>

            <!-- Description -->
            <div class="text-gray-600 mb-6 space-y-3">
                <p>تم اكتشاف نشاط مشبوه من عنوان IP الخاص بك.</p>
                <p>يبدو أنك تستخدم أدوات آلية (bot) للوصول إلى موقعنا.</p>
            </div>

            <!-- What to do -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2">ماذا يمكنك فعله؟</h3>
                <ul class="text-sm text-blue-800 space-y-1 text-right">
                    <li>• تأكد من أنك تستخدم متصفح ويب عادي</li>
                    <li>• تأكد من تمكين JavaScript</li>
                    <li>• قم بمسح ملفات تعريف الارتباط والمحاولة مرة أخرى</li>
                    <li>• إذا كنت تستخدم VPN، حاول إيقافه</li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="text-sm text-gray-500 mb-6">
                <p>إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع فريق الدعم.</p>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button onclick="window.location.reload()" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    المحاولة مرة أخرى
                </button>
                
                <a href="/" 
                   class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-200">
                    العودة للصفحة الرئيسية
                </a>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-xs text-gray-400">
                    Error Code: 403 - Bot Detected<br>
                    IP: {{ request()->ip() }}<br>
                    Time: {{ now()->format('Y-m-d H:i:s') }}
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-retry after 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>