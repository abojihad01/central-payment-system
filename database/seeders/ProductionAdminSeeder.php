<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate secure password
        $password = $this->generateSecurePassword();
        
        $admin = \App\Models\User::firstOrCreate([
            'email' => 'admin@' . config('app.domain', 'example.com'),
        ], [
            'name' => 'System Administrator',
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
            'two_factor_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Store password in a secure file (remove after first login)
        $credentialsFile = storage_path('app/production_admin_credentials.txt');
        $credentials = "Central Payment System - Production Admin Credentials\n";
        $credentials .= "Generated: " . now()->toDateTimeString() . "\n";
        $credentials .= "===================================================\n\n";
        $credentials .= "Email: " . $admin->email . "\n";
        $credentials .= "Password: " . $password . "\n\n";
        $credentials .= "⚠️  IMPORTANT SECURITY NOTES:\n";
        $credentials .= "1. Change this password immediately after first login\n";
        $credentials .= "2. Enable Two-Factor Authentication\n";
        $credentials .= "3. Delete this file after setting up your account\n";
        $credentials .= "4. Use a password manager for secure storage\n\n";
        $credentials .= "File location: " . $credentialsFile . "\n";
        $credentials .= "Delete command: rm " . $credentialsFile . "\n";

        file_put_contents($credentialsFile, $credentials);
        chmod($credentialsFile, 0600); // Only owner can read

        $this->command->info('Production admin user created successfully!');
        $this->command->warn('Credentials saved to: ' . $credentialsFile);
        $this->command->warn('⚠️  IMPORTANT: Delete this file after first login!');
    }

    /**
     * Generate a secure password
     */
    private function generateSecurePassword(): string
    {
        // Generate a strong password with mixed characters
        $length = 16;
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        
        // Ensure at least one character from each category
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest randomly
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
}