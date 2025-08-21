@extends('layouts.app')

@section('content')
<style>
    * {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    
    .minimal-page {
        background: #ffffff;
        min-height: 100vh;
        padding: 80px 0;
    }
    
    .main-container {
        max-width: 720px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .success-badge {
        display: inline-flex;
        align-items: center;
        background: #f0fdf4;
        color: #16a34a;
        padding: 8px 16px;
        border-radius: 100px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 24px;
    }
    
    .success-badge svg {
        width: 16px;
        height: 16px;
        margin-right: 8px;
    }
    
    .page-title {
        font-size: 36px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 12px;
        line-height: 1.2;
    }
    
    .page-subtitle {
        font-size: 18px;
        color: #6b7280;
        margin-bottom: 60px;
        font-weight: 400;
    }
    
    .progress-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 80px;
        position: relative;
    }
    
    .progress-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 1px;
        background: #e5e7eb;
        z-index: 0;
    }
    
    .step-item {
        flex: 1;
        text-align: center;
        position: relative;
        z-index: 1;
    }
    
    .step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        border: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 14px;
        font-weight: 600;
        color: #9ca3af;
    }
    
    .step-item.completed .step-circle {
        background: #10b981;
        border-color: #10b981;
        color: white;
    }
    
    .step-item.active .step-circle {
        background: #111827;
        border-color: #111827;
        color: white;
    }
    
    .step-label {
        font-size: 14px;
        color: #9ca3af;
    }
    
    .step-item.completed .step-label,
    .step-item.active .step-label {
        color: #111827;
        font-weight: 500;
    }
    
    .form-section {
        margin-bottom: 48px;
    }
    
    .section-label {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .device-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 48px;
    }
    
    .device-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        padding: 32px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
        position: relative;
    }
    
    .device-card:hover {
        border-color: #d1d5db;
        transform: translateY(-2px);
    }
    
    .device-card.selected {
        border-color: #111827;
        background: #fafafa;
    }
    
    .device-card input {
        position: absolute;
        opacity: 0;
    }
    
    .device-emoji {
        font-size: 48px;
        margin-bottom: 16px;
    }
    
    .device-name {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }
    
    .device-desc {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.5;
    }
    
    .minimal-select {
        width: 100%;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        font-size: 16px;
        color: #111827;
        background: white;
        transition: all 0.2s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 20px;
        padding-right: 48px;
    }
    
    .minimal-select:focus {
        outline: none;
        border-color: #111827;
    }
    
    .minimal-textarea {
        width: 100%;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        font-size: 16px;
        color: #111827;
        background: white;
        transition: all 0.2s ease;
        resize: vertical;
        min-height: 120px;
        font-family: inherit;
    }
    
    .minimal-textarea:focus {
        outline: none;
        border-color: #111827;
    }
    
    .info-card {
        background: #f9fafb;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 48px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    
    .info-item {
        text-align: center;
    }
    
    .info-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    .submit-button {
        width: 100%;
        padding: 20px;
        background: #111827;
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .submit-button:hover {
        background: #1f2937;
        transform: translateY(-1px);
    }
    
    .submit-button:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }
    
    @media (max-width: 640px) {
        .device-cards {
            grid-template-columns: 1fr;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .info-item {
            text-align: left;
        }
    }
</style>

<div class="minimal-page">
    <div class="main-container">
        <!-- Success Badge -->
        <div class="success-badge">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            تم الدفع بنجاح
        </div>
        
        <!-- Title -->
        <h1 class="page-title">اختر تفضيلات جهازك</h1>
        <p class="page-subtitle">قم بتخصيص اشتراكك وفقاً لاحتياجاتك</p>
        
        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step-item completed">
                <div class="step-circle">✓</div>
                <div class="step-label">الدفع</div>
            </div>
            <div class="step-item active">
                <div class="step-circle">2</div>
                <div class="step-label">التكوين</div>
            </div>
            <div class="step-item">
                <div class="step-circle">3</div>
                <div class="step-label">التفعيل</div>
            </div>
        </div>

        <!-- Form -->
        <form id="deviceSelectionForm" method="POST" action="{{ route('devices.save-selection', $payment->id) }}">
            @csrf
            
            <!-- Device Type -->
            <div class="form-section">
                <div class="section-label">نوع الجهاز</div>
                <div class="device-cards">
                    <label for="mag" class="device-card">
                        <input type="radio" name="type" id="mag" value="MAG" required>
                        <div class="device-emoji">📺</div>
                        <div class="device-name">MAG Device</div>
                        <div class="device-desc">للأجهزة الصندوقية<br>MAG 250, 322, etc</div>
                    </label>
                    
                    <label for="m3u" class="device-card">
                        <input type="radio" name="type" id="m3u" value="M3U" required>
                        <div class="device-emoji">📱</div>
                        <div class="device-name">M3U Link</div>
                        <div class="device-desc">للتطبيقات<br>والأجهزة الذكية</div>
                    </label>
                </div>
            </div>

            <!-- Package Selection -->
            <div class="form-section">
                <label for="pack_id" class="section-label">الباقة</label>
                <select class="minimal-select" id="pack_id" name="pack_id" required>
                    <option value="">اختر الباقة المناسبة لك</option>
                    <option value="1">الباقة الأساسية - Basic</option>
                    <option value="2">الباقة المتقدمة - Premium</option>
                    <option value="3">الباقة الشاملة - Ultimate</option>
                </select>
            </div>

            <!-- Subscription Duration (From Plan) -->
            <div class="form-section">
                <div class="section-label">مدة الاشتراك</div>
                <div style="padding: 16px; background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <div style="font-size: 18px; font-weight: 600; color: #111827;">
                        {{ $payment->plan ? $payment->plan->getDurationDisplayText() : 'شهر واحد' }}
                    </div>
                    <div style="font-size: 14px; color: #6b7280; margin-top: 4px;">
                        الخطة: {{ $payment->plan->name ?? 'الخطة الأساسية' }}
                    </div>
                </div>
                <input type="hidden" name="sub_duration" value="{{ $payment->plan ? $payment->plan->getDurationInMonths() : 1 }}">
            </div>

            <!-- Country -->
            <div class="form-section">
                <label for="country" class="section-label">الدولة</label>
                <select class="minimal-select" id="country" name="country" required>
                    <option value="">اختر دولتك</option>
                    <option value="SA">السعودية</option>
                    <option value="AE">الإمارات</option>
                    <option value="KW">الكويت</option>
                    <option value="QA">قطر</option>
                    <option value="BH">البحرين</option>
                    <option value="OM">عمان</option>
                    <option value="EG">مصر</option>
                    <option value="JO">الأردن</option>
                    <option value="LB">لبنان</option>
                    <option value="US">الولايات المتحدة</option>
                    <option value="GB">بريطانيا</option>
                    <option value="DE">ألمانيا</option>
                </select>
            </div>

            <!-- Notes -->
            <div class="form-section">
                <label for="notes" class="section-label">ملاحظات إضافية</label>
                <textarea class="minimal-textarea" id="notes" name="notes" placeholder="اكتب أي ملاحظات أو متطلبات خاصة هنا (اختياري)"></textarea>
            </div>

            <!-- Payment Info -->
            <div class="info-card">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">رقم العملية</div>
                        <div class="info-value">#{{ $payment->id }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">المبلغ المدفوع</div>
                        <div class="info-value">{{ $payment->amount }} {{ $payment->currency }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">البريد الإلكتروني</div>
                        <div class="info-value">{{ $payment->customer_email }}</div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-button">
                تفعيل الاشتراك
            </button>
        </form>
    </div>
</div>

<script>
// Handle device type selection
document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove selected class from all cards
        document.querySelectorAll('.device-card').forEach(card => {
            card.classList.remove('selected');
        });
        // Add selected class to parent label
        this.closest('.device-card').classList.add('selected');
    });
});

// Handle form submission
document.getElementById('deviceSelectionForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('.submit-button');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = 'جاري المعالجة...';
    submitBtn.disabled = true;
});
</script>

@endsection
