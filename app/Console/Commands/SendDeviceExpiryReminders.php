<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Customer;
use App\Notifications\DeviceExpiringReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class SendDeviceExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:send-expiry-reminders {--days=3 : Days before expiry to send reminder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiry reminder notifications for devices expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $targetDate = now()->addDays($days)->format('Y-m-d');
        
        $this->info("Checking for devices expiring on {$targetDate}...");
        
        // Find devices expiring in specified days
        $expiringDevices = Device::with('customer')
            ->where('status', 'enable')
            ->whereDate('expire_date', $targetDate)
            ->whereDoesntHave('logs', function ($query) use ($days) {
                // Check if reminder was already sent in last 24 hours
                $query->where('action', 'expiry_reminder')
                    ->where('created_at', '>=', now()->subDay());
            })
            ->get();
        
        if ($expiringDevices->isEmpty()) {
            $this->info('No devices found expiring in ' . $days . ' days.');
            return Command::SUCCESS;
        }
        
        $this->info('Found ' . $expiringDevices->count() . ' devices expiring soon.');
        
        $remindersSent = 0;
        $errors = 0;
        
        foreach ($expiringDevices as $device) {
            try {
                // Send notification to customer
                if ($device->customer && $device->customer->email) {
                    Notification::send($device->customer, new DeviceExpiringReminder($device));
                    
                    // Log the reminder
                    $device->logAction(
                        'expiry_reminder',
                        "Expiry reminder sent for {$days} days before expiration",
                        ['days_before' => $days, 'expire_date' => $device->expire_date->format('Y-m-d')]
                    );
                    
                    $remindersSent++;
                    $this->info("Reminder sent for device #{$device->id} (Customer: {$device->customer->email})");
                    
                    Log::info('Device expiry reminder sent', [
                        'device_id' => $device->id,
                        'customer_email' => $device->customer->email,
                        'expire_date' => $device->expire_date->format('Y-m-d'),
                        'days_remaining' => $days
                    ]);
                } else {
                    $this->warn("Device #{$device->id} has no customer email associated");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Failed to send reminder for device #{$device->id}: " . $e->getMessage());
                
                Log::error('Failed to send device expiry reminder', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("Process completed: {$remindersSent} reminders sent, {$errors} errors");
        
        return Command::SUCCESS;
    }
}
