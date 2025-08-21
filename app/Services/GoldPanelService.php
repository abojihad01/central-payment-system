<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\Package;
use App\Models\ResellerInfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GoldPanelService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?ResellerInfo $reseller;

    public function __construct()
    {
        $this->baseUrl = config('services.gold_panel.base_url', 'https://goldpanel.net/api');
        $this->apiKey = config('services.gold_panel.api_key') ?: null;
        $this->reseller = ResellerInfo::getActive();
    }

    /**
     * Create MAG device
     */
    public function createMagDevice(array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/mag/add', [
                    'api_key' => $this->apiKey,
                    'mac' => $data['mac'] ?? $this->generateMac(),
                    'pack_id' => $data['pack_id'],
                    'sub_duration' => $data['sub_duration'],
                    'country' => $data['country'] ?? 'US',
                    'notes' => $data['notes'] ?? ''
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to create MAG device: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'user_id' => $result['user_id'] ?? null,
                'mac' => $result['mac'] ?? $data['mac'],
                'url' => $result['portal_url'] ?? null,
                'expire_date' => $result['expire_date'] ?? null,
                'message' => $result['message'] ?? 'Device created successfully',
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel MAG creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Create M3U device
     */
    public function createM3uDevice(array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/m3u/add', [
                    'api_key' => $this->apiKey,
                    'username' => $data['username'] ?? $this->generateUsername(),
                    'password' => $data['password'] ?? $this->generatePassword(),
                    'pack_id' => $data['pack_id'],
                    'sub_duration' => $data['sub_duration'],
                    'country' => $data['country'] ?? 'US',
                    'notes' => $data['notes'] ?? ''
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to create M3U device: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'user_id' => $result['user_id'] ?? null,
                'username' => $result['username'] ?? $data['username'],
                'password' => $result['password'] ?? $data['password'],
                'url' => $result['m3u_url'] ?? null,
                'expire_date' => $result['expire_date'] ?? null,
                'message' => $result['message'] ?? 'Device created successfully',
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel M3U creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Renew device subscription
     */
    public function renewDevice(int $userId, int $months): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/renew', [
                    'api_key' => $this->apiKey,
                    'user_id' => $userId,
                    'months' => $months
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to renew device: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'expire_date' => $result['new_expire_date'] ?? null,
                'message' => $result['message'] ?? 'Device renewed successfully',
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel renewal failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Get device info
     */
    public function getDeviceInfo(int $userId): array
    {
        try {
            $response = Http::timeout(30)
                ->get($this->baseUrl . '/info', [
                    'api_key' => $this->apiKey,
                    'user_id' => $userId
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get device info: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'data' => $result,
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel info fetch failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Change device status
     */
    public function changeStatus(int $userId, string $status): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/status', [
                    'api_key' => $this->apiKey,
                    'user_id' => $userId,
                    'status' => $status // 'enable' or 'disable'
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to change device status: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Status changed successfully',
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel status change failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Get reseller info
     */
    public function getResellerInfo(): array
    {
        try {
            $response = Http::timeout(30)
                ->get($this->baseUrl . '/reseller/info', [
                    'api_key' => $this->apiKey
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get reseller info: ' . $response->body());
            }

            $result = $response->json();
            
            // Update local reseller info
            if ($this->reseller) {
                $this->reseller->updateFromApi($result);
            }
            
            return [
                'success' => true,
                'data' => $result,
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel reseller info failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Get available packages
     */
    public function getPackages(): array
    {
        try {
            $response = Http::timeout(30)
                ->get($this->baseUrl . '/packages', [
                    'api_key' => $this->apiKey
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get packages: ' . $response->body());
            }

            $result = $response->json();
            
            // Update local packages
            if (isset($result['packages']) && is_array($result['packages'])) {
                foreach ($result['packages'] as $package) {
                    Package::findOrCreateFromApi(
                        $package['id'],
                        $package['name']
                    );
                }
            }
            
            return [
                'success' => true,
                'packages' => $result['packages'] ?? [],
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            Log::error('GoldPanel packages fetch failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'raw_response' => null
            ];
        }
    }

    /**
     * Generate random MAC address
     */
    protected function generateMac(): string
    {
        $mac = [];
        for ($i = 0; $i < 6; $i++) {
            $mac[] = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
        }
        return strtoupper(implode(':', $mac));
    }

    /**
     * Generate random username
     */
    protected function generateUsername(): string
    {
        return 'user_' . strtolower(\Str::random(8));
    }

    /**
     * Generate random password
     */
    protected function generatePassword(): string
    {
        return \Str::random(12);
    }

    /**
     * Check if reseller has enough credits
     */
    public function checkCredits(int $required = 1): bool
    {
        if (!$this->reseller) {
            return false;
        }

        return $this->reseller->hasCredits($required);
    }
}
