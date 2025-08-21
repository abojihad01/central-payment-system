@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-center">اختر خطة IPTV الخاصة بك</h1>
        
        <form id="device-selection-form" class="space-y-6 bg-white p-8 rounded-lg shadow-lg">
            @csrf
            
            <!-- Device Type Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">نوع الجهاز</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="device_type" value="MAG" class="hidden peer" required>
                        <div class="border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-600 peer-checked:text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                            </svg>
                            <h3 class="font-semibold">MAG Device</h3>
                            <p class="text-sm text-gray-500 mt-1">للأجهزة من نوع MAG</p>
                        </div>
                    </label>
                    
                    <label class="cursor-pointer">
                        <input type="radio" name="device_type" value="M3U" class="hidden peer" required>
                        <div class="border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-600 peer-checked:text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 7.5a.5.5 0 01.5-.5h3a.5.5 0 010 1h-3a.5.5 0 01-.5-.5zm0 3a.5.5 0 01.5-.5h3a.5.5 0 010 1h-3a.5.5 0 01-.5-.5z"/>
                            </svg>
                            <h3 class="font-semibold">M3U Link</h3>
                            <p class="text-sm text-gray-500 mt-1">للتطبيقات والأجهزة الذكية</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Package Selection -->
            <div>
                <label for="package" class="block text-sm font-medium text-gray-700 mb-2">اختر الباقة</label>
                <select name="pack_id" id="package" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- اختر الباقة --</option>
                    @foreach(\App\Models\Package::all() as $package)
                        <option value="{{ $package->api_pack_id }}">{{ $package->name }}</option>
                    @endforeach
                    @if(\App\Models\Package::count() == 0)
                        <option value="1">Basic Package</option>
                        <option value="2">Premium Package</option>
                        <option value="3">VIP Package</option>
                    @endif
                </select>
            </div>

            <!-- Duration Selection -->
            <div>
                <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">مدة الاشتراك</label>
                <select name="sub_duration" id="duration" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- اختر المدة --</option>
                    <option value="1">شهر واحد</option>
                    <option value="3">3 أشهر</option>
                    <option value="6">6 أشهر</option>
                    <option value="12">سنة كاملة</option>
                </select>
            </div>

            <!-- Country Selection -->
            <div>
                <label for="country" class="block text-sm font-medium text-gray-700 mb-2">الدولة</label>
                <select name="country" id="country" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- اختر الدولة --</option>
                    <option value="SA">المملكة العربية السعودية</option>
                    <option value="AE">الإمارات العربية المتحدة</option>
                    <option value="KW">الكويت</option>
                    <option value="QA">قطر</option>
                    <option value="BH">البحرين</option>
                    <option value="OM">عمان</option>
                    <option value="EG">مصر</option>
                    <option value="JO">الأردن</option>
                    <option value="LB">لبنان</option>
                    <option value="IQ">العراق</option>
                    <option value="SY">سوريا</option>
                    <option value="YE">اليمن</option>
                    <option value="LY">ليبيا</option>
                    <option value="TN">تونس</option>
                    <option value="DZ">الجزائر</option>
                    <option value="MA">المغرب</option>
                    <option value="US">الولايات المتحدة</option>
                    <option value="GB">المملكة المتحدة</option>
                    <option value="DE">ألمانيا</option>
                    <option value="FR">فرنسا</option>
                    <option value="OTHER">أخرى</option>
                </select>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">ملاحظات (اختياري)</label>
                <textarea name="notes" id="notes" rows="3" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="أي ملاحظات إضافية..."></textarea>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" id="email" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="your@email.com">
            </div>

            <!-- Price Display -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold">السعر الإجمالي:</span>
                    <span id="total-price" class="text-2xl font-bold text-blue-600">$0.00</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                المتابعة إلى الدفع
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('device-selection-form');
    const durationSelect = document.getElementById('duration');
    const packageSelect = document.getElementById('package');
    const priceDisplay = document.getElementById('total-price');
    
    // Price calculation (example prices - adjust as needed)
    const basePrices = {
        '1': { 1: 10, 3: 25, 6: 45, 12: 80 },  // Basic
        '2': { 1: 15, 3: 40, 6: 70, 12: 130 }, // Premium
        '3': { 1: 25, 3: 65, 6: 120, 12: 220 } // VIP
    };
    
    function updatePrice() {
        const packId = packageSelect.value;
        const duration = durationSelect.value;
        
        if (packId && duration && basePrices[packId]) {
            const price = basePrices[packId][duration] || 0;
            priceDisplay.textContent = `$${price}.00`;
        } else {
            priceDisplay.textContent = '$0.00';
        }
    }
    
    packageSelect.addEventListener('change', updatePrice);
    durationSelect.addEventListener('change', updatePrice);
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const deviceData = {
            type: formData.get('device_type'),
            pack_id: formData.get('pack_id'),
            sub_duration: formData.get('sub_duration'),
            country: formData.get('country'),
            notes: formData.get('notes'),
            email: formData.get('email')
        };
        
        // Store selection in session or pass to payment gateway
        fetch('/devices/prepare-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(deviceData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.payment_url) {
                window.location.href = data.payment_url;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ. يرجى المحاولة مرة أخرى.');
        });
    });
});
</script>
@endsection
