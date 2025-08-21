<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Subscription;
use App\Services\GoldPanelService;
use App\Notifications\DeviceCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessGoldPanelDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subscription;
    protected $deviceData;

    /**
     * Create a new job instance.
     */
    public function __construct(Subscription $subscription, array $deviceData)
    {
        $this->subscription = $subscription;
        $this->deviceData = $deviceData;
    }

    /**
     * Execute the job.
     */
    public function handle(GoldPanelService $goldPanel)
    {
        try {
            // Get or create customer
            $customer = $this->getOrCreateCustomer();

            // Check reseller credits
            if (!$goldPanel->checkCredits()) {
                Log::error('Insufficient reseller credits for device creation', [
                    'subscription_id' => $this->subscription->id
                ]);
                return;
            }

            // Create device based on type
            $apiResponse = $this->deviceData['type'] === 'MAG' 
                ? $goldPanel->createMagDevice($this->deviceData)
                : $goldPanel->createM3uDevice($this->deviceData);

            if (!$apiResponse['success']) {
                Log::error('Failed to create device via API', [
                    'subscription_id' => $this->subscription->id,
                    'error' => $apiResponse['message']
                ]);
                return;
            }

            // Save device to database
            $device = $this->saveDevice($customer, $apiResponse);

            // Log the action
            $device->logAction('add', $apiResponse['message'], $apiResponse['raw_response']);

            // Update subscription with device info
            $this->subscription->update([
                'metadata' => array_merge($this->subscription->metadata ?? [], [
                    'device_id' => $device->id,
                    'device_type' => $device->type
                ])
            ]);

            // Send notification to customer
            if ($customer && $customer->email) {
                Notification::send($customer, new DeviceCreated($device));
            }

            Log::info('Device created successfully', [
                'device_id' => $device->id,
                'subscription_id' => $this->subscription->id
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing Gold Panel device', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retry the job after 5 minutes
            $this->release(300);
        }
    }

    /**
     * Get or create customer
     */
    protected function getOrCreateCustomer(): Customer
    {
        $payment = $this->subscription->payment;
        
        return Customer::firstOrCreate(
            ['email' => $payment->customer_email],
            [
                'name' => $payment->customer_name ?? 'Customer',
                'phone' => $payment->customer_phone,
                'total_spent' => 0,
                'total_subscriptions' => 0
            ]
        );
    }

    /**
     * Save device to database
     */
    protected function saveDevice(Customer $customer, array $apiResponse): Device
    {
        $credentials = [];
        
        if ($this->deviceData['type'] === 'MAG') {
            $credentials = ['mac' => $apiResponse['mac']];
        } else {
            $credentials = [
                'username' => $apiResponse['username'],
                'password' => $apiResponse['password']
            ];
        }

        return Device::create([
            'customer_id' => $customer->id,
            'type' => $this->deviceData['type'],
            'credentials' => $credentials,
            'pack_id' => $this->deviceData['pack_id'],
            'sub_duration' => $this->deviceData['sub_duration'],
            'notes' => $this->deviceData['notes'] ?? null,
            'country' => $this->deviceData['country'] ?? 'US',
            'api_user_id' => $apiResponse['user_id'],
            'url' => $apiResponse['url'],
            'status' => 'enable',
            'expire_date' => $apiResponse['expire_date'] ?? now()->addMonths($this->deviceData['sub_duration'])
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Gold Panel device job failed permanently', [
            'subscription_id' => $this->subscription->id,
            'error' => $exception->getMessage()
        ]);
    }
}
