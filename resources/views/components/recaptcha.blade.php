@props([
    'action' => 'submit',
    'threshold' => 0.5,
    'required' => true,
    'version' => 'v3' // v2, v3
])

@if(config('services.recaptcha.site_key'))
<div class="recaptcha-container" {{ $attributes->merge(['class' => 'mb-4']) }}>
    @if($required)
        @if($version === 'v3')
            {{-- reCAPTCHA v3 (Invisible) --}}
            <div class="recaptcha-v3-container" 
                 data-sitekey="{{ config('services.recaptcha.site_key') }}"
                 data-action="{{ $action }}"
                 data-threshold="{{ $threshold }}">
                
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-{{ $action }}">
                
                @error('recaptcha')
                    <div class="bg-red-50 border border-red-200 rounded-md p-3 mt-2">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-red-800">{{ $message }}</p>
                            </div>
                        </div>
                    </div>
                @enderror
            </div>
            
            {{-- reCAPTCHA branding (required by Google for v3) --}}
            <div class="recaptcha-branding text-xs text-gray-500 mt-2">
                This site is protected by reCAPTCHA and the Google
                <a href="https://policies.google.com/privacy" target="_blank" class="text-blue-600 hover:underline">Privacy Policy</a> and
                <a href="https://policies.google.com/terms" target="_blank" class="text-blue-600 hover:underline">Terms of Service</a> apply.
            </div>
        @else
            {{-- reCAPTCHA v2 (Visible Checkbox) --}}
            <div class="g-recaptcha" 
                 data-sitekey="{{ config('services.recaptcha.site_key') }}"
                 data-theme="light"
                 data-size="normal"
                 data-callback="onRecaptchaSuccess"
                 data-expired-callback="onRecaptchaExpired">
            </div>
            
            @error('recaptcha')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        @endif
    @endif
</div>

@once
    @push('scripts')
        @if($version === 'v3')
            {{-- reCAPTCHA v3 Implementation --}}
            <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}" async defer></script>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const siteKey = '{{ config('services.recaptcha.site_key') }}';
                let recaptchaReady = false;
                
                // Initialize when reCAPTCHA is ready
                function initRecaptchaV3() {
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(function() {
                            recaptchaReady = true;
                            setupFormHandlers();
                        });
                    } else {
                        setTimeout(initRecaptchaV3, 100);
                    }
                }
                
                function setupFormHandlers() {
                    const forms = document.querySelectorAll('form');
                    
                    forms.forEach(function(form) {
                        const recaptchaContainer = form.querySelector('.recaptcha-v3-container');
                        if (!recaptchaContainer || form.dataset.recaptchaV3Attached) return;
                        
                        const action = recaptchaContainer.dataset.action || 'submit';
                        const threshold = parseFloat(recaptchaContainer.dataset.threshold) || 0.5;
                        
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            if (!recaptchaReady) {
                                console.error('reCAPTCHA not ready');
                                return;
                            }
                            
                            // Show loading state
                            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                            const originalText = submitBtn ? submitBtn.textContent : '';
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.textContent = 'Verifying...';
                            }
                            
                            grecaptcha.execute(siteKey, { action: action })
                                .then(function(token) {
                                    // Store token
                                    const tokenInput = form.querySelector('#g-recaptcha-response-' + action);
                                    if (tokenInput) {
                                        tokenInput.value = token;
                                    }
                                    
                                    // Submit form
                                    form.submit();
                                })
                                .catch(function(error) {
                                    console.error('reCAPTCHA error:', error);
                                    
                                    // Restore button state
                                    if (submitBtn) {
                                        submitBtn.disabled = false;
                                        submitBtn.textContent = originalText;
                                    }
                                    
                                    // Check if we're in development and should bypass
                                    if (window.location.hostname === 'localhost' || 
                                        window.location.hostname === '127.0.0.1' ||
                                        window.location.hostname.includes('.test')) {
                                        
                                        console.warn('reCAPTCHA failed in development, submitting anyway');
                                        const tokenInput = form.querySelector('#g-recaptcha-response-' + action);
                                        if (tokenInput) {
                                            tokenInput.value = 'dev-bypass-token';
                                        }
                                        form.submit();
                                    } else {
                                        showRecaptchaError('Verification failed. Please try again.');
                                    }
                                });
                        });
                        
                        form.dataset.recaptchaV3Attached = 'true';
                    });
                }
                
                function showRecaptchaError(message) {
                    // Remove existing errors
                    document.querySelectorAll('.recaptcha-error').forEach(el => el.remove());
                    
                    // Create error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'recaptcha-error bg-red-50 border border-red-200 rounded-md p-3 mt-3';
                    errorDiv.innerHTML = `
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-red-800">${message}</p>
                            </div>
                        </div>
                    `;
                    
                    const container = document.querySelector('.recaptcha-v3-container');
                    if (container) {
                        container.parentNode.insertBefore(errorDiv, container.nextSibling);
                    }
                }
                
                initRecaptchaV3();
            });
            </script>
        @else
            {{-- reCAPTCHA v2 Implementation --}}
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            
            <script>
            let recaptchaValid = false;
            
            function onRecaptchaSuccess(token) {
                recaptchaValid = true;
                console.log('reCAPTCHA verified successfully');
            }
            
            function onRecaptchaExpired() {
                recaptchaValid = false;
                console.log('reCAPTCHA expired');
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.reset();
                }
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                // Reset reCAPTCHA on form errors
                @if($errors->any())
                    setTimeout(function() {
                        if (typeof grecaptcha !== 'undefined' && document.querySelector('.g-recaptcha')) {
                            grecaptcha.reset();
                            recaptchaValid = false;
                        }
                    }, 1000);
                @endif
                
                // Form validation
                const forms = document.querySelectorAll('form');
                forms.forEach(function(form) {
                    // Check if form contains reCAPTCHA
                    if (!form.querySelector('.g-recaptcha')) return;
                    form.addEventListener('submit', function(e) {
                        if (typeof grecaptcha !== 'undefined') {
                            const response = grecaptcha.getResponse();
                            if (response.length === 0) {
                                e.preventDefault();
                                alert('يرجى إكمال التحقق من reCAPTCHA / Please complete the reCAPTCHA verification');
                                return false;
                            }
                        }
                    });
                });
            });
            </script>
        @endif
    @endpush
@endonce

@else
    {{-- reCAPTCHA not configured --}}
    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
        <div class="flex">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">reCAPTCHA Configuration Required</h3>
                <div class="mt-1 text-sm text-yellow-700">
                    <p>reCAPTCHA is not configured. Please set up your reCAPTCHA keys:</p>
                    <ol class="list-decimal list-inside mt-2 space-y-1">
                        <li>Go to <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="text-blue-600 hover:underline font-medium">Google reCAPTCHA Admin Console</a></li>
                        <li>Create a new site with reCAPTCHA v3</li>
                        <li>Add your domain(s) to the site settings</li>
                        <li>Copy the Site Key and Secret Key to your <code class="bg-gray-100 px-1 rounded">.env</code> file:</li>
                    </ol>
                    <div class="bg-gray-50 border border-gray-200 rounded mt-2 p-2 font-mono text-xs">
                        RECAPTCHA_SITE_KEY=your_site_key_here<br>
                        RECAPTCHA_SECRET_KEY=your_secret_key_here
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif