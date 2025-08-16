<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <form wire:submit="generateLink">
                {{ $this->form }}
                
                <div class="mt-6">
                    <x-filament::button
                        type="submit"
                        color="primary"
                        size="lg"
                        icon="heroicon-o-plus"
                    >
                        إنشاء رابط الدفع
                    </x-filament::button>
                    
                    <x-filament::button
                        type="button"
                        color="gray"
                        outlined
                        wire:click="resetForm"
                        class="ml-3"
                    >
                        إعادة تعيين
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if($generatedLink)
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-green-900 dark:text-green-100">تم إنشاء الرابط بنجاح!</h3>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-green-800 dark:text-green-200 mb-2">رابط الدفع:</label>
                    <div class="relative">
                        <textarea 
                            readonly 
                            class="w-full p-3 text-sm bg-white dark:bg-gray-700 border border-green-300 dark:border-green-600 rounded-lg resize-none"
                            rows="3"
                            id="generated-link"
                        >{{ $generatedLink }}</textarea>
                        
                        <button 
                            type="button"
                            onclick="copyGeneratedLink()"
                            class="absolute top-2 right-2 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 dark:bg-green-800 dark:text-green-100 dark:hover:bg-green-700 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            نسخ
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a 
                        href="{{ $generatedLink }}" 
                        target="_blank"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        اختبار الرابط
                    </a>
                </div>
            </div>
        @endif
    </div>

    <script>
    function copyGeneratedLink() {
        const textarea = document.getElementById('generated-link');
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = `<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>تم النسخ!`;
            button.classList.add('bg-green-200', 'text-green-800');
            button.classList.remove('bg-green-100', 'text-green-700');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('bg-green-200', 'text-green-800');
                button.classList.add('bg-green-100', 'text-green-700');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }
    </script>
</x-filament-panels::page>