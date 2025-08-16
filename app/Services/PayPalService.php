<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $mode;

    public function __construct(?PaymentAccount $account = null)
    {
        if ($account) {
            $this->clientId = $account->credentials['client_id'];
            $this->clientSecret = $account->credentials['client_secret'];
            $this->baseUrl = $account->credentials['api_base_url'];
            $this->mode = $account->credentials['environment'];
        } else {
            // Fallback to config
            $this->mode = config('services.paypal.mode', 'sandbox');
            $this->clientId = config("services.paypal.{$this->mode}.client_id");
            $this->clientSecret = config("services.paypal.{$this->mode}.client_secret");
            $this->baseUrl = $this->mode === 'sandbox' 
                ? 'https://api-m.sandbox.paypal.com' 
                : 'https://api-m.paypal.com';
        }
    }

    /**
     * Get PayPal access token
     */
    public function getAccessToken(): ?string
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            Log::error('PayPal access token request failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('PayPal access token exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Test PayPal connection
     */
    public function testConnection(): array
    {
        $token = $this->getAccessToken();
        
        if ($token) {
            return [
                'success' => true,
                'message' => 'PayPal connection successful',
                'mode' => $this->mode,
                'client_id' => substr($this->clientId, 0, 10) . '...'
            ];
        }

        return [
            'success' => false,
            'message' => 'PayPal connection failed - invalid credentials',
            'mode' => $this->mode
        ];
    }

    /**
     * Create PayPal order
     */
    public function createOrder(Payment $payment): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to get PayPal access token'
            ];
        }

        try {
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $payment->currency ?? 'USD',
                            'value' => number_format($payment->amount, 2, '.', '')
                        ],
                        'description' => 'Payment for subscription',
                        'custom_id' => $payment->id
                    ]
                ],
                'application_context' => [
                    'return_url' => url("/payments/{$payment->id}/success"),
                    'cancel_url' => url("/payments/{$payment->id}/cancel"),
                    'brand_name' => config('app.name', 'Payment System'),
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW'
                ]
            ];

            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/v2/checkout/orders", $orderData);

            if ($response->successful()) {
                $order = $response->json();
                
                return [
                    'success' => true,
                    'order_id' => $order['id'],
                    'approval_url' => collect($order['links'])
                        ->firstWhere('rel', 'approve')['href'] ?? null,
                    'order_data' => $order
                ];
            }

            Log::error('PayPal order creation failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'payment_id' => $payment->id
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create PayPal order',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('PayPal order creation exception', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id
            ]);

            return [
                'success' => false,
                'error' => 'PayPal order creation exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Capture PayPal order
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to get PayPal access token'
            ];
        }

        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

            if ($response->successful()) {
                $capture = $response->json();
                
                return [
                    'success' => true,
                    'status' => $capture['status'],
                    'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                    'capture_data' => $capture
                ];
            }

            Log::error('PayPal order capture failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'order_id' => $orderId
            ]);

            return [
                'success' => false,
                'error' => 'Failed to capture PayPal order',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('PayPal order capture exception', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);

            return [
                'success' => false,
                'error' => 'PayPal capture exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get order details
     */
    public function getOrder(string $orderId): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to get PayPal access token'
            ];
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/v2/checkout/orders/{$orderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'order' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get PayPal order details',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'PayPal get order exception: ' . $e->getMessage()
            ];
        }
    }
}