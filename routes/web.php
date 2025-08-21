<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('nothing-to-see');
});

// Payment routes with bot protection
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/process-payment', [PaymentController::class, 'process'])->middleware('bot.protection')->name('process-payment');
Route::get('/process-payment', [PaymentController::class, 'handleGetRequest'])->name('process-payment.get');
Route::get('/payment/verify/{payment}', [PaymentController::class, 'verify'])->name('payment.verify');
Route::get('/payment/verify-session/{sessionId}', [PaymentController::class, 'verifyBySession'])->name('payment.verify.session');
Route::get('/payment-success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment-cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
Route::get('/paypal-cancel', [PaymentController::class, 'paypalCancel'])->name('paypal.cancel');
Route::get('/paypal-return', [PaymentController::class, 'paypalReturn'])->name('paypal.return');


// Test Routes (remove in production)
Route::get('/test-recaptcha', function () {
    return view('test-recaptcha');
});

Route::post('/test-recaptcha', function (Illuminate\Http\Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'website_url' => [new App\Rules\HoneypotRule('website_url')],
        'email_confirmation' => [new App\Rules\HoneypotRule('email_confirmation')],
    ]);
    
    // Validate reCAPTCHA
    $recaptchaService = app(App\Services\RecaptchaService::class);
    $validation = $recaptchaService->validateRequest($request, 'contact');
    
    if (!$validation['valid']) {
        return back()->withErrors(['recaptcha' => $validation['message']]);
    }
    
    return back()->with('success', 'Form submitted successfully! reCAPTCHA score: ' . $validation['score']);
})->middleware('bot.protection');

// Webhooks
Route::post('/webhooks/stripe', [PaymentController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [PaymentController::class, 'paypalWebhook'])->name('webhooks.paypal');

// Device management routes
Route::prefix('devices')->group(function () {
    Route::get('/select/{paymentId}', [DeviceController::class, 'selectAfterPayment'])->name('devices.select-after-payment');
    Route::post('/save-selection/{paymentId}', [DeviceController::class, 'saveDeviceSelection'])->name('devices.save-selection');
    Route::get('/', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/{device}/download', [DeviceController::class, 'downloadM3U'])->name('devices.download');
    Route::post('/{device}/renew', [DeviceController::class, 'renew'])->name('devices.renew');
    Route::post('/{device}/toggle', [DeviceController::class, 'toggleStatus'])->name('devices.toggle');
    Route::post('/{device}/sync', [DeviceController::class, 'syncInfo'])->name('devices.sync');
});
Route::get('/customer/list', [DeviceController::class, 'customerDevices'])->name('devices.customer');
