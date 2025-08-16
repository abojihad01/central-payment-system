<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>reCAPTCHA Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-lg mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">reCAPTCHA Test Form</h1>
        
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <p class="text-green-800">{{ session('success') }}</p>
            </div>
        @endif
        
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <ul class="text-red-800 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="/test-recaptcha" class="space-y-6">
            @csrf
            
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Name
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="{{ old('name') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                    required>
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                    required>
            </div>
            
            <!-- Honeypot Protection -->
            <x-honeypot />
            
            <!-- reCAPTCHA v3 -->
            <x-recaptcha action="contact" threshold="0.5" version="v3" />
            
            <button 
                type="submit"
                class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700">
                Submit (Protected by reCAPTCHA v3)
            </button>
        </form>
        
        <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="font-medium text-blue-900">Configuration Status</h3>
            <div class="mt-2 text-sm text-blue-800">
                <p><strong>Site Key:</strong> {{ config('services.recaptcha.site_key') ? 'Configured ✓' : 'Missing ✗' }}</p>
                <p><strong>Secret Key:</strong> {{ config('services.recaptcha.secret_key') ? 'Configured ✓' : 'Missing ✗' }}</p>
                <p><strong>reCAPTCHA:</strong> {{ App\Models\BotProtectionSettings::get('recaptcha_enabled') ? 'Enabled ✓' : 'Disabled ✗' }}</p>
                <p><strong>Threshold:</strong> {{ App\Models\BotProtectionSettings::get('recaptcha_threshold') }}</p>
            </div>
        </div>
    </div>
    
    @stack('scripts')
</body>
</html>