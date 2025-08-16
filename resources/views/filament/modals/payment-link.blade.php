<div class="space-y-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
            معلومات الرابط
        </h3>
        
        <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">الموقع:</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $record->website->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">الباقة:</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $record->plan->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">السعر:</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">${{ number_format($record->price, 2) }} {{ $record->currency }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">حالة الرابط:</dt>
                <dd>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $record->isValid() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $record->isValid() ? 'صالح' : 'غير صالح' }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h4 class="text-md font-medium text-blue-900 dark:text-blue-100 mb-3">
            رابط الدفع
        </h4>
        
        <div class="relative">
            <textarea 
                readonly 
                class="w-full p-3 text-sm bg-white dark:bg-gray-700 border border-blue-200 dark:border-blue-700 rounded-lg resize-none"
                rows="3"
                id="payment-link-text"
            >{{ $link }}</textarea>
            
            <button 
                onclick="copyToClipboard()"
                class="absolute top-2 right-2 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-800 dark:text-blue-100 dark:hover:bg-blue-700 transition-colors"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                نسخ
            </button>
        </div>
        
        <div class="mt-3 flex space-x-3 rtl:space-x-reverse">
            <a 
                href="{{ $link }}" 
                target="_blank"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors"
            >
                <svg class="w-4 h-4 mr-2 rtl:ml-2 rtl:mr-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                فتح الرابط
            </a>
            
            <button 
                onclick="generateQR()"
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
            >
                <svg class="w-4 h-4 mr-2 rtl:ml-2 rtl:mr-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
                إنشاء QR Code
            </button>
        </div>
    </div>

    <!-- QR Code Container -->
    <div id="qr-code-container" class="hidden bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
        <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">QR Code</h4>
        <div id="qr-code" class="flex justify-center"></div>
    </div>
</div>

<script>
function copyToClipboard() {
    const textarea = document.getElementById('payment-link-text');
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        // Show success message
        const button = event.target.closest('button');
        const originalText = button.innerHTML;
        button.innerHTML = `<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>تم النسخ!`;
        button.classList.add('bg-green-100', 'text-green-700');
        button.classList.remove('bg-blue-100', 'text-blue-700');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('bg-green-100', 'text-green-700');
            button.classList.add('bg-blue-100', 'text-blue-700');
        }, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
}

function generateQR() {
    const container = document.getElementById('qr-code-container');
    const qrDiv = document.getElementById('qr-code');
    
    // Clear previous QR code
    qrDiv.innerHTML = '';
    
    // Show container
    container.classList.remove('hidden');
    
    // Generate QR code using a simple service
    const qrText = encodeURIComponent('{{ $link }}');
    const qrImage = document.createElement('img');
    qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${qrText}`;
    qrImage.alt = 'QR Code for Payment Link';
    qrImage.className = 'mx-auto border rounded-lg';
    
    qrDiv.appendChild(qrImage);
    
    // Add download button
    const downloadBtn = document.createElement('a');
    downloadBtn.href = qrImage.src;
    downloadBtn.download = 'payment-qr-code.png';
    downloadBtn.className = 'inline-flex items-center mt-3 px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors';
    downloadBtn.innerHTML = `<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
    </svg>تحميل`;
    
    qrDiv.appendChild(downloadBtn);
}
</script>