<?php

namespace App\Http\Controllers;

use App\Services\PaymentLinkService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private PaymentLinkService $paymentLinkService,
        private PaymentGatewayService $gatewayService
    ) {}

    public function show(Request $request): View
    {
        $token = $request->query('token');
        
        if (!$token) {
            abort(400, 'Payment token is required');
        }

        try {
            $data = $this->paymentLinkService->validateAndDecodeToken($token);
            
            // تعيين اللغة بناءً على لغة الموقع
            $language = $data['website_language'] ?? 'en';
            app()->setLocale($language);
            
            // الحصول على مفتاح Stripe للعملة المحددة
            $stripePublishableKey = null;
            $gateway = $this->gatewayService->selectBestGateway($data['currency'], 'US');
            if ($gateway && $gateway->name === 'stripe') {
                $account = $this->gatewayService->selectBestAccount($gateway);
                if ($account && isset($account->credentials['publishable_key'])) {
                    $stripePublishableKey = $account->credentials['publishable_key'];
                }
            }

            // تحديد البوابة المُفضلة (الأقل استخداماً) لعرض الشهر المجاني
            $promotedPaymentMethod = $this->gatewayService->getPromotedPaymentMethod();
            $paymentComparison = $this->gatewayService->getPaymentMethodComparison();
            
            return view('checkout', [
                'paymentData' => $data,
                'token' => $token,
                'stripePublishableKey' => $stripePublishableKey,
                'language' => $language,
                'isRTL' => in_array($language, ['ar', 'he', 'fa', 'ur']),
                'promotedPaymentMethod' => $promotedPaymentMethod,
                'paymentComparison' => $paymentComparison
            ]);
        } catch (\Exception $e) {
            // محاولة استخراج اللغة من التوكن حتى لو كان خاطئاً
            $language = 'en'; // Default to English for errors
            $isRTL = false;
            
            try {
                // Try to extract language from token even if token is invalid
                if ($token) {
                    $data = $this->paymentLinkService->validateAndDecodeToken($token);
                    $language = $data['website_language'] ?? 'en';
                    $isRTL = in_array($language, ['ar', 'he', 'fa', 'ur']);
                }
            } catch (\Exception $tokenException) {
                // If we can't extract language from token, try to decode without validation
                try {
                    $parts = explode('.', $token);
                    if (count($parts) >= 2) {
                        $payload = json_decode(base64_decode($parts[1]), true);
                        if (isset($payload['data']['website_language'])) {
                            $language = $payload['data']['website_language'];
                            $isRTL = in_array($language, ['ar', 'he', 'fa', 'ur']);
                        }
                    }
                } catch (\Exception $decodeException) {
                    // Use default English if all fails
                }
            }
            
            app()->setLocale($language);
            
            // عرض صفحة خطأ مخصصة مع تفاصيل أكثر
            return view('payment-error', [
                'error' => $e->getMessage(),
                'message' => $this->getErrorMessage($e->getMessage()),
                'language' => $language,
                'isRTL' => $isRTL
            ]);
        }
    }
    
    private function getErrorMessage(string $error): string
    {
        if (str_contains($error, 'no longer valid')) {
            return __('payment.error_expired_link');
        }
        
        if (str_contains($error, 'not found')) {
            return __('payment.error_not_found');
        }
        
        return __('payment.error_general');
    }
}