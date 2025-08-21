@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Device Details</h1>

        {{-- Success/Error Messages --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Device Info Card --}}
        <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-semibold">{{ $device->type }} Device</h2>
                <span class="px-3 py-1 rounded-full text-sm font-semibold
                    {{ $device->status === 'enable' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ ucfirst($device->status) }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">Customer</p>
                    <p class="font-medium">{{ $device->customer->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Email</p>
                    <p class="font-medium">{{ $device->customer->email ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Package</p>
                    <p class="font-medium">{{ $device->package->name ?? 'Standard Package' }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Duration</p>
                    <p class="font-medium">{{ $device->sub_duration }} months</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Created</p>
                    <p class="font-medium">{{ $device->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Expires</p>
                    <p class="font-medium {{ $device->isExpiringSoon() ? 'text-red-600' : '' }}">
                        {{ $device->expire_date->format('M d, Y') }}
                        @if($device->isExpiringSoon())
                            <span class="text-xs">(Expiring Soon!)</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Credentials Card --}}
        <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Access Credentials</h2>
            
            @if($device->type === 'MAG')
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-gray-600 text-sm mb-1">MAC Address</p>
                        <p class="font-mono font-medium text-lg">{{ $credentials['MAC Address'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-gray-600 text-sm mb-1">Portal URL</p>
                        <p class="font-mono text-sm break-all">{{ $credentials['Portal URL'] ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 rounded">
                    <h3 class="font-semibold text-blue-900 mb-2">Setup Instructions:</h3>
                    <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                        <li>Open your MAG device settings</li>
                        <li>Navigate to System Settings > Servers</li>
                        <li>Enter the Portal URL provided above</li>
                        <li>Save and restart your device</li>
                    </ol>
                </div>
            @else
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-gray-600 text-sm mb-1">Username</p>
                        <p class="font-mono font-medium text-lg">{{ $credentials['Username'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-gray-600 text-sm mb-1">Password</p>
                        <p class="font-mono font-medium text-lg">{{ $credentials['Password'] ?? 'N/A' }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-gray-600 text-sm mb-1">M3U URL</p>
                        <p class="font-mono text-sm break-all">{{ $credentials['M3U URL'] ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('devices.download-m3u', $device->id) }}" 
                       class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Download M3U File
                    </a>
                </div>

                <div class="mt-4 p-4 bg-blue-50 rounded">
                    <h3 class="font-semibold text-blue-900 mb-2">Setup Instructions:</h3>
                    <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                        <li>Download the M3U file or copy the M3U URL</li>
                        <li>Open your IPTV player (VLC, GSE Smart IPTV, etc.)</li>
                        <li>Add a new playlist using the M3U URL</li>
                        <li>Enter your username and password when prompted</li>
                    </ol>
                </div>
            @endif
        </div>

        {{-- Actions Card --}}
        <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Actions</h2>
            
            <div class="flex flex-wrap gap-3">
                {{-- Renew Button --}}
                <button onclick="showRenewModal()" 
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Renew Subscription
                </button>

                {{-- Toggle Status --}}
                <form action="{{ route('devices.toggle-status', $device->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            class="px-4 py-2 rounded {{ $device->status === 'enable' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700' }} text-white">
                        {{ $device->status === 'enable' ? 'Disable' : 'Enable' }} Device
                    </button>
                </form>

                {{-- Sync Info --}}
                <form action="{{ route('devices.sync', $device->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        Sync Info
                    </button>
                </form>
            </div>
        </div>

        {{-- Recent Activity --}}
        @if($device->logs->count() > 0)
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-gray-600">Date</th>
                            <th class="text-left py-2 text-gray-600">Action</th>
                            <th class="text-left py-2 text-gray-600">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($device->logs as $log)
                        <tr class="border-b">
                            <td class="py-2 text-sm">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            <td class="py-2 text-sm">
                                <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ $log->action }}</span>
                            </td>
                            <td class="py-2 text-sm">{{ $log->message }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Renew Modal --}}
<div id="renewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Renew Subscription</h3>
        
        <form action="{{ route('devices.renew', $device->id) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Select Duration (Months)
                </label>
                <select name="months" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    <option value="1">1 Month</option>
                    <option value="3">3 Months</option>
                    <option value="6">6 Months</option>
                    <option value="12">12 Months</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="hideRenewModal()" 
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    Renew
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRenewModal() {
    document.getElementById('renewModal').classList.remove('hidden');
}

function hideRenewModal() {
    document.getElementById('renewModal').classList.add('hidden');
}
</script>
@endsection
