<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\Package;
use App\Models\Payment;
use App\Models\GeneratedLink;
use App\Models\Plan;
use App\Models\Website;
use App\Models\Customer;
use App\Models\Subscription;
use App\Jobs\ProcessGoldPanelDevice;
use App\Services\GoldPanelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class DeviceController extends Controller
{
    protected ?GoldPanelService $goldPanel = null;

    public function __construct()
    {
        // Initialize GoldPanel service only if API key is configured
        if (config('services.gold_panel.api_key')) {
            try {
                $this->goldPanel = app(GoldPanelService::class);
            } catch (\Exception $e) {
                \Log::warning('GoldPanel service initialization failed: ' . $e->getMessage());
                $this->goldPanel = null;
            }
        }
    }

    /**
     * Display device details after payment
     */
    public function show($deviceId)
    {
        $device = Device::with(['customer', 'package', 'logs' => function ($query) {
            $query->latest()->limit(5);
        }])->findOrFail($deviceId);

        // Format credentials for display
        $credentials = $device->getFormattedCredentials();

        return view('devices.show', compact('device', 'credentials'));
    }

    /**
     * Display customer's devices
     */
    public function customerDevices(Request $request)
    {
        $email = $request->get('email');
        
        if (!$email) {
            return redirect()->back()->with('error', 'Email is required');
        }

        $customer = Customer::where('email', $email)->first();
        
        if (!$customer) {
            return redirect()->back()->with('error', 'No devices found for this email');
        }

        $devices = Device::where('customer_id', $customer->id)
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('devices.index', compact('devices', 'customer'));
    }

    /**
     * Download M3U file
     */
    public function downloadM3u($deviceId)
    {
        $device = Device::findOrFail($deviceId);

        if ($device->type !== 'M3U') {
            return redirect()->back()->with('error', 'This device does not support M3U download');
        }

        $credentials = $device->credentials;
        $m3uContent = $this->generateM3uContent($device);

        return Response::make($m3uContent, 200, [
            'Content-Type' => 'audio/x-mpegurl',
            'Content-Disposition' => 'attachment; filename="playlist_' . $credentials['username'] . '.m3u"'
        ]);
    }

    /**
     * Renew device subscription
     */
    public function renew(Request $request, $deviceId)
    {
        $request->validate([
            'months' => 'required|integer|min:1|max:12'
        ]);

        $device = Device::findOrFail($deviceId);

        if (!$device->api_user_id) {
            return redirect()->back()->with('error', 'Device not linked to Gold Panel');
        }

        // Call Gold Panel API to renew
        $result = $this->goldPanel->renewDevice($device->api_user_id, $request->months);

        if ($result['success']) {
            // Update device expiry date
            $device->update([
                'expire_date' => $result['expire_date'],
                'sub_duration' => $device->sub_duration + $request->months
            ]);

            // Log the renewal
            $device->logAction('renew', $result['message'], $result['raw_response']);

            return redirect()->back()->with('success', 'Device renewed successfully until ' . $device->expire_date->format('Y-m-d'));
        }

        return redirect()->back()->with('error', 'Failed to renew device: ' . $result['message']);
    }

    /**
     * Toggle device status
     */
    public function toggleStatus($deviceId)
    {
        $device = Device::findOrFail($deviceId);

        if (!$device->api_user_id) {
            return redirect()->back()->with('error', 'Device not linked to Gold Panel');
        }

        $newStatus = $device->status === 'enable' ? 'disable' : 'enable';

        // Call Gold Panel API to change status
        $result = $this->goldPanel->changeStatus($device->api_user_id, $newStatus);

        if ($result['success']) {
            // Update device status
            $device->update(['status' => $newStatus]);

            // Log the status change
            $device->logAction('status_change', $result['message'], $result['raw_response']);

            return redirect()->back()->with('success', 'Device status changed to ' . $newStatus);
        }

        return redirect()->back()->with('error', 'Failed to change device status: ' . $result['message']);
    }

    /**
     * Sync device info from Gold Panel
     */
    public function syncInfo($deviceId)
    {
        $device = Device::findOrFail($deviceId);

        if (!$device->api_user_id) {
            return redirect()->back()->with('error', 'Device not linked to Gold Panel');
        }

        // Fetch latest info from Gold Panel
        $result = $this->goldPanel->getDeviceInfo($device->api_user_id);

        if ($result['success']) {
            $data = $result['data'];
            
            // Update device info
            $device->update([
                'expire_date' => $data['expire_date'] ?? $device->expire_date,
                'status' => $data['status'] ?? $device->status
            ]);

            // Log the sync
            $device->logAction('info_fetch', 'Device info synced', $result['raw_response']);

            return redirect()->back()->with('success', 'Device information updated');
        }

        return redirect()->back()->with('error', 'Failed to sync device info: ' . $result['message']);
    }

    /**
     * Generate M3U content
     */
    protected function generateM3uContent(Device $device): string
    {
        $credentials = $device->credentials;
        $m3uContent = "#EXTM3U\n";
        $m3uContent .= "#EXT-X-VERSION:3\n";
        $m3uContent .= "#EXT-X-STREAM-INF:BANDWIDTH=1000000\n";
        $m3uContent .= $device->url . "\n";
        
        return $m3uContent;
    }

    /**
     * Show device selection form after successful payment
     */
    public function selectAfterPayment($paymentId)
    {
        // Verify payment exists and is completed
        $payment = Payment::where('id', $paymentId)
                         ->where('status', 'completed')
                         ->first();
        
        if (!$payment) {
            return redirect('/')->with('error', 'دفعة غير صالحة أو غير مكتملة');
        }
        
        // Check if device already created for this payment
        if (Device::where('payment_id', $payment->id)->exists()) {
            return redirect()->route('devices.index')
                           ->with('info', 'تم إنشاء الجهاز بالفعل لهذه الدفعة');
        }
        
        $packages = Package::all();
        return view('devices.select-after-payment', [
            'packages' => $packages,
            'payment' => $payment
        ]);
    }

    /**
     * Save device selection after payment
     */
    public function saveDeviceSelection(Request $request, $paymentId)
    {
        // Verify payment
        $payment = Payment::where('id', $paymentId)
                         ->where('status', 'completed')
                         ->first();
        
        if (!$payment) {
            return response()->json(['error' => 'دفعة غير صالحة'], 400);
        }
        
        // Check if device already exists
        if (Device::where('payment_id', $payment->id)->exists()) {
            return response()->json(['error' => 'تم إنشاء الجهاز بالفعل'], 400);
        }
        
        $validated = $request->validate([
            'type' => 'required|in:MAG,M3U',
            'pack_id' => 'required|integer',
            'sub_duration' => 'required|integer|in:1,3,6,12',
            'country' => 'required|string|size:2',
            'notes' => 'nullable|string'
        ]);

        // Update payment with device data
        $payment->gateway_response = array_merge(
            $payment->gateway_response ?? [],
            [
                'metadata' => array_merge(
                    $payment->gateway_response['metadata'] ?? [],
                    [
                        'gold_panel_device' => [
                            'type' => $validated['type'],
                            'pack_id' => $validated['pack_id'],
                            'sub_duration' => $validated['sub_duration'],
                            'country' => $validated['country'],
                            'notes' => $validated['notes'] ?? '',
                            'payment_id' => $payment->id
                        ]
                    ]
                )
            ]
        );
        $payment->save();
        
        // Get or create subscription
        $subscription = $payment->subscription;
        if (!$subscription) {
            $subscription = Subscription::create([
                'customer_id' => Customer::firstOrCreate(
                    ['email' => $payment->customer_email],
                    ['name' => $payment->customer_name ?? 'Customer']
                )->id,
                'payment_id' => $payment->id,
                'plan_id' => $payment->plan_id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths($validated['sub_duration'])
            ]);
        }
        
        // Dispatch job to create device
        ProcessGoldPanelDevice::dispatch($subscription, $validated)->delay(now()->addSeconds(5));
        
        return response()->json([
            'success' => true,
            'message' => 'تم حفظ اختيار الجهاز وسيتم إنشاؤه قريباً',
            'redirect' => route('devices.index')
        ]);
    }
}
