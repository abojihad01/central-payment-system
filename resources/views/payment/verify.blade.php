<!DOCTYPE html>
<html lang="{{ $language ?? app()->getLocale() }}" dir="{{ $isRTL ?? (in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'])) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payment Verification') }} - {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* RTL Support */
        [dir="rtl"] {
            text-align: right;
        }
        
        [dir="rtl"] .verify-card {
            direction: rtl;
        }
        
        .verify-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 50px 40px;
            max-width: 550px;
            width: 90%;
            text-align: center;
            line-height: 1.6;
        }
        
        .loading-spinner {
            width: 80px;
            height: 80px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 30px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-icon {
            font-size: 80px;
            margin: 20px 0;
        }
        
        .status-icon.success {
            color: #28a745;
        }
        
        .status-icon.error {
            color: #dc3545;
        }
        
        .status-icon.processing {
            color: #007bff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .status-text {
            font-size: 24px;
            font-weight: 600;
            margin: 25px 0;
            letter-spacing: 0.5px;
            word-spacing: 2px;
        }
        
        .status-description {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.8;
            word-spacing: 1px;
            padding: 0 15px;
        }
        
        .progress-bar {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .btn-custom {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 25px;
            color: white;
            padding: 15px 35px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease;
            letter-spacing: 0.5px;
            margin: 10px 5px;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.3);
            background: transparent;
            color: rgba(255,255,255,0.8);
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }
        
        .success-animation {
            animation: successPulse 2s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .countdown-urgent {
            animation: countdownBlink 1s infinite;
        }
        
        @keyframes countdownBlink {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: right;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        
        .detail-value {
            color: #007bff;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div id="verification-content">
            <!-- Loading State -->
            <div id="loading-state">
                <div class="loading-spinner"></div>
                <div class="status-text" id="status-text">{{ __('payment.payment_being_verified') }}</div>
                <div class="status-description" id="status-description">
                    {{ __('payment.server_connection_message') }}
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="progress-fill"></div>
                </div>
                <div class="payment-details">
                    @if(isset($payment))
                    <div class="detail-row">
                        <span class="detail-label">{{ __('payment.payment_id') }}:</span>
                        <span class="detail-value">&nbsp;&nbsp;#{{ $payment->id }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('payment.payment_amount') }}:</span>
                        <span class="detail-value">&nbsp;&nbsp;{{ $payment->amount }} {{ strtoupper($payment->currency) }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Plan') }}:</span>
                        <span class="detail-value">&nbsp;&nbsp;{{ $payment->plan->name ?? __('Not specified') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Payment Method') }}:</span>
                        <span class="detail-value">&nbsp;&nbsp;{{ ucfirst($payment->payment_gateway) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Success State -->
            <div id="success-state" style="display: none;">
                <div class="status-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-text text-success">✅ {{ __('payment.payment_confirmed') }}</div>
                <div class="status-description">
                    🎉 &nbsp; {{ __('payment.payment_successful') }}
                    <br><strong>✅ &nbsp; {{ __('payment.transaction_complete') }}</strong>
                    <br><small class="text-muted mt-2">💡 &nbsp; {{ __('payment.close_window') }}</small>
                </div>
                <div class="payment-details" id="success-details">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="mt-4">
                    <a href="#" id="success-redirect" class="btn-custom me-3">
                        <i class="fas fa-arrow-right me-2"></i>
                        &nbsp; {{ __('payment.continue_to_website') }}
                    </a>
                    <button onclick="window.close()" class="btn btn-outline-light">
                        <i class="fas fa-times me-2"></i>
                        &nbsp; {{ __('Close') }}
                    </button>
                </div>
            </div>

            <!-- Error State -->
            <div id="error-state" style="display: none;">
                <div class="status-icon error">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="status-text text-danger">{{ __('payment.payment_failed') }}</div>
                <div class="status-description" id="error-message">
                    {{ __('payment.payment_not_completed') }}
                </div>
                <div class="payment-details" id="error-details">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="mt-4">
                    <a href="#" id="retry-payment" class="btn-custom me-3">
                        <i class="fas fa-redo me-2"></i>
                        {{ __('payment.try_again') }}
                    </a>
                    <button onclick="attemptRecovery()" class="btn btn-success me-3" id="recovery-btn">
                        <i class="fas fa-search me-2"></i>
                        {{ __('payment.check_payment_status') }}
                    </button>
                    <a href="#" onclick="alert('{{ __('payment.please_contact_support') }}')" class="btn btn-outline-secondary">
                        <i class="fas fa-life-ring me-2"></i>
                        {{ __('payment.contact_support') }}
                    </a>
                </div>
            </div>

            <!-- Processing State (for pending) -->
            <div id="processing-state" style="display: none;">
                <div class="status-icon processing">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="status-text text-info">{{ __('payment.payment_processing') }}</div>
                <div class="status-description">
                    {{ __('payment.payment_taking_longer') }}...
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 50%;"></div>
                </div>
                <div class="payment-details" id="processing-details">
                    <!-- Will be populated by JavaScript -->
                </div>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    &nbsp; {{ __('payment.verification_takes_time') }}
                </small>
            </div>
        </div>
    </div>

    <script>
        // Translation variables
        const translations = {
            connecting: "{{ __('payment.connecting_to_server') }}",
            verifying: "{{ __('payment.verifying_payment_info') }}",
            confirmed: "{{ __('payment.payment_confirmed') }}",
            failed: "{{ __('payment.payment_failed') }}",
            processing: "{{ __('payment.payment_processing') }}",
            payment_id: "{{ __('payment.payment_id') }}",
            payment_amount: "{{ __('payment.payment_amount') }}",
            preparing_subscription: "{{ __('payment.preparing_subscription') }}",
            connection_failed: "{{ __('payment.connection_failed') }}",
            status: "{{ __('Status') }}",
            date: "{{ __('Date') }}",
            plan: "{{ __('Plan') }}",
            not_specified: "{{ __('Not specified') }}",
            payment_method: "{{ __('Payment Method') }}",
            status_pending: "{{ __('payment.status_pending') }}",
            status_completed: "{{ __('payment.status_completed') }}",
            status_failed: "{{ __('payment.status_failed') }}",
            status_cancelled: "{{ __('payment.status_cancelled') }}",
            checking_status: "{{ __('payment.checking_payment_status') }}",
            recovery_success: "{{ __('payment.recovery_successful') }}",
            recovery_failed: "{{ __('payment.recovery_failed') }}"
        };

        class PaymentVerifier {
            constructor() {
                this.paymentId = '{{ $payment->id ?? "" }}';
                this.sessionId = '{{ request("session_id") ?? "" }}';
                this.checkInterval = null;
                this.attempts = 0;
                this.maxAttempts = 60; // 5 minutes with 5-second intervals
                this.progress = 0;
                this.isActive = true;
                
                this.init();
                this.setupBeforeUnloadHandler();
            }
            
            init() {
                if (!this.paymentId) {
                    this.showError(translations.connection_failed);
                    return;
                }
                
                this.startVerification();
            }
            
            setupBeforeUnloadHandler() {
                // Clean up when page is closed or refreshed
                window.addEventListener('beforeunload', () => {
                    this.cleanup();
                });
                
                // Handle visibility change (tab switching)
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.pauseVerification();
                    } else if (this.isActive) {
                        this.resumeVerification();
                    }
                });
                
                // Handle page focus/blur
                window.addEventListener('blur', () => {
                    this.pauseVerification();
                });
                
                window.addEventListener('focus', () => {
                    if (this.isActive) {
                        this.resumeVerification();
                    }
                });
            }
            
            cleanup() {
                this.isActive = false;
                if (this.checkInterval) {
                    clearInterval(this.checkInterval);
                    this.checkInterval = null;
                }
                
                // Notify server about abandonment (fire and forget)
                if (this.paymentId) {
                    fetch(`/api/payment/${this.paymentId}/abandon`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            reason: 'page_closed',
                            attempts: this.attempts
                        })
                    }).catch(() => {
                        // Silently fail - this is just for tracking
                    });
                }
            }
            
            pauseVerification() {
                if (this.checkInterval) {
                    clearInterval(this.checkInterval);
                    this.checkInterval = null;
                }
            }
            
            resumeVerification() {
                if (this.isActive && !this.checkInterval && this.attempts < this.maxAttempts) {
                    this.checkInterval = setInterval(() => {
                        this.checkPaymentStatus();
                    }, 5000);
                }
            }
            
            startVerification() {
                this.updateProgress(10);
                this.updateStatus(translations.connecting, translations.verifying);
                
                // Start checking immediately
                this.checkPaymentStatus();
                
                // Then check every 5 seconds
                this.checkInterval = setInterval(() => {
                    this.checkPaymentStatus();
                }, 5000);
            }
            
            async checkPaymentStatus() {
                // Check if verification is still active
                if (!this.isActive) {
                    return;
                }
                
                this.attempts++;
                this.updateProgress(Math.min(90, (this.attempts / this.maxAttempts) * 90));
                
                if (this.attempts > this.maxAttempts) {
                    this.showProcessing();
                    return;
                }
                
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
                    
                    const response = await fetch(`/api/payment/verify/${this.paymentId}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        if (response.status === 404) {
                            throw new Error(translations.connection_failed + ' (Payment not found)');
                        } else if (response.status >= 500) {
                            throw new Error(translations.connection_failed + ' (Server error)');
                        } else {
                            throw new Error(translations.connection_failed + ` (HTTP ${response.status})`);
                        }
                    }
                    
                    const data = await response.json();
                    this.handleResponse(data);
                    
                } catch (error) {
                    console.error('Verification error:', error);
                    
                    // Handle specific error types
                    if (error.name === 'AbortError') {
                        console.log('Request timeout');
                        // Continue trying on timeout unless max attempts reached
                        if (this.attempts >= this.maxAttempts) {
                            this.showError(translations.connection_failed + ' (Timeout)');
                        }
                    } else if (!navigator.onLine) {
                        // Network is offline
                        this.showError(translations.connection_failed + ' (No internet connection)');
                    } else if (this.attempts >= this.maxAttempts) {
                        this.showError(translations.connection_failed + ': ' + error.message);
                    }
                    // For other errors, continue trying unless max attempts reached
                }
            }
            
            handleResponse(data) {
                if (data.status === 'completed') {
                    // Show success message and animation first
                    this.updateStatus(translations.confirmed, translations.verifying);
                    this.updateProgress(100);
                    
                    // Give user time to see the success message
                    setTimeout(() => {
                        this.showSuccess(data);
                    }, 2000);
                } else if (data.status === 'failed' || data.status === 'cancelled') {
                    this.showError(data.message || 'فشل في عملية الدفع', data);
                } else {
                    // Still pending, continue checking
                    this.updateStatus(
                        'جاري التحقق من الدفع...',
                        `المحاولة ${this.attempts} من ${this.maxAttempts}`
                    );
                }
            }
            
            showSuccess(data) {
                this.stopChecking();
                this.updateProgress(100);
                
                document.getElementById('loading-state').style.display = 'none';
                const successState = document.getElementById('success-state');
                successState.style.display = 'block';
                successState.classList.add('success-animation');
                
                // Populate success details
                if (data.payment) {
                    document.getElementById('success-details').innerHTML = this.buildDetailsHTML(data.payment);
                }
                
                // Update redirect URL
                const redirectBtn = document.getElementById('success-redirect');
                if (data.redirect_url) {
                    redirectBtn.href = data.redirect_url;
                    
                    // Auto-redirect after 3 seconds for device selection pages
                    if (data.redirect_url.includes('/devices/select/')) {
                        let countdown = 3;
                        redirectBtn.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>التوجيه لاختيار الجهاز خلال ${countdown} ثواني...`;
                        
                        const countdownInterval = setInterval(() => {
                            countdown--;
                            if (countdown > 0) {
                                redirectBtn.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>التوجيه لاختيار الجهاز خلال ${countdown} ثواني...`;
                            } else {
                                clearInterval(countdownInterval);
                                window.location.href = data.redirect_url;
                            }
                        }, 1000);
                        
                        // Allow immediate redirect on click
                        redirectBtn.onclick = (e) => {
                            e.preventDefault();
                            clearInterval(countdownInterval);
                            window.location.href = data.redirect_url;
                        };
                    } else {
                        // For other pages, show button without auto-redirect
                        redirectBtn.innerHTML = `<i class="fas fa-check me-2"></i>المتابعة للصفحة التالية`;
                        redirectBtn.onclick = (e) => {
                            e.preventDefault();
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            }
                        };
                    }
                } else {
                    // No redirect URL, just show success
                    redirectBtn.innerHTML = `<i class="fas fa-check me-2"></i>العودة للصفحة الرئيسية`;
                    redirectBtn.href = '/';
                }
            }
            
            showError(message, data = null) {
                this.stopChecking();
                
                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('error-state').style.display = 'block';
                
                document.getElementById('error-message').textContent = message;
                
                if (data && data.payment) {
                    document.getElementById('error-details').innerHTML = this.buildDetailsHTML(data.payment);
                }
                
                // Set retry URL
                const retryBtn = document.getElementById('retry-payment');
                if (data && data.retry_url) {
                    retryBtn.href = data.retry_url;
                } else {
                    retryBtn.onclick = () => location.reload();
                }
            }
            
            showProcessing() {
                this.stopChecking();
                
                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('processing-state').style.display = 'block';
                
                // Show payment details in processing state
                const payment = @json($payment ?? null);
                if (payment) {
                    document.getElementById('processing-details').innerHTML = this.buildDetailsHTML(payment);
                }
            }
            
            updateStatus(title, description) {
                document.getElementById('status-text').textContent = title;
                document.getElementById('status-description').textContent = description;
            }
            
            updateProgress(percentage) {
                this.progress = Math.min(100, Math.max(0, percentage));
                document.getElementById('progress-fill').style.width = this.progress + '%';
            }
            
            buildDetailsHTML(payment) {
                return `
                    <div class="detail-row">
                        <span class="detail-label">${translations.payment_id}:</span>
                        <span class="detail-value">&nbsp;&nbsp;#${payment.id}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">${translations.payment_amount}:</span>
                        <span class="detail-value">&nbsp;&nbsp;${payment.amount} ${payment.currency.toUpperCase()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">${translations.status}:</span>
                        <span class="detail-value">&nbsp;&nbsp;${this.getStatusText(payment.status)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">${translations.date}:</span>
                        <span class="detail-value">&nbsp;&nbsp;${new Date(payment.created_at).toLocaleString()}</span>
                    </div>
                `;
            }
            
            getStatusText(status) {
                const statusMap = {
                    'pending': translations.status_pending,
                    'completed': translations.status_completed,
                    'failed': translations.status_failed,
                    'cancelled': translations.status_cancelled
                };
                return statusMap[status] || status;
            }
            
            stopChecking() {
                if (this.checkInterval) {
                    clearInterval(this.checkInterval);
                    this.checkInterval = null;
                }
            }
        }
        
        // Recovery function for interrupted verifications
        async function attemptRecovery() {
            const paymentId = '{{ $payment->id ?? "" }}';
            if (!paymentId) {
                alert('Payment ID not available for recovery');
                return;
            }
            
            const recoveryBtn = document.getElementById('recovery-btn');
            const originalText = recoveryBtn.innerHTML;
            
            // Update button to show loading state
            recoveryBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + translations.checking_status;
            recoveryBtn.disabled = true;
            
            try {
                const response = await fetch(`/api/payment/${paymentId}/recover`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        recovery_attempt: true,
                        timestamp: new Date().toISOString()
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'completed') {
                    // Payment was successfully recovered
                    document.getElementById('error-state').style.display = 'none';
                    
                    // Show success state
                    const successState = document.getElementById('success-state');
                    successState.style.display = 'block';
                    successState.classList.add('success-animation');
                    
                    // Populate success details
                    if (data.payment) {
                        document.getElementById('success-details').innerHTML = buildDetailsHTML(data.payment);
                    }
                    
                    // Update redirect URL
                    const redirectBtn = document.getElementById('success-redirect');
                    if (data.redirect_url) {
                        redirectBtn.href = data.redirect_url;
                        redirectBtn.onclick = (e) => {
                            e.preventDefault();
                            window.location.href = data.redirect_url;
                        };
                    }
                    
                } else if (data.status === 'failed') {
                    // Payment failed during recovery
                    document.getElementById('error-message').textContent = data.message || translations.recovery_failed;
                    if (data.payment) {
                        document.getElementById('error-details').innerHTML = buildDetailsHTML(data.payment);
                    }
                } else {
                    // Still pending - restart verification process
                    document.getElementById('error-state').style.display = 'none';
                    document.getElementById('loading-state').style.display = 'block';
                    
                    // Create new verifier instance
                    new PaymentVerifier();
                }
                
            } catch (error) {
                console.error('Recovery attempt failed:', error);
                alert(translations.recovery_failed + ': ' + error.message);
            } finally {
                // Restore button state
                recoveryBtn.innerHTML = originalText;
                recoveryBtn.disabled = false;
            }
        }
        
        // Helper function to build details HTML (same as in PaymentVerifier class)
        function buildDetailsHTML(payment) {
            return `
                <div class="detail-row">
                    <span class="detail-label">${translations.payment_id}:</span>
                    <span class="detail-value">&nbsp;&nbsp;#${payment.id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">${translations.payment_amount}:</span>
                    <span class="detail-value">&nbsp;&nbsp;${payment.amount} ${payment.currency.toUpperCase()}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">${translations.status}:</span>
                    <span class="detail-value">&nbsp;&nbsp;${getStatusText(payment.status)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">${translations.date}:</span>
                    <span class="detail-value">&nbsp;&nbsp;${new Date(payment.created_at).toLocaleString()}</span>
                </div>
            `;
        }
        
        // Helper function to get status text
        function getStatusText(status) {
            const statusMap = {
                'pending': translations.status_pending,
                'completed': translations.status_completed,
                'failed': translations.status_failed,
                'cancelled': translations.status_cancelled
            };
            return statusMap[status] || status;
        }

        // Initialize payment verifier when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new PaymentVerifier();
        });
    </script>
</body>
</html>