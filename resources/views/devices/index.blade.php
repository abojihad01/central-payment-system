@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Your Devices</h1>

        {{-- Customer Info --}}
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-600 text-sm">Customer</p>
                    <p class="font-semibold">{{ $customer->name }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Email</p>
                    <p class="font-semibold">{{ $customer->email }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Total Devices</p>
                    <p class="font-semibold">{{ $devices->total() }}</p>
                </div>
            </div>
        </div>

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

        {{-- Devices List --}}
        @if($devices->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($devices as $device)
                    <div class="bg-white shadow-lg rounded-lg p-6 hover:shadow-xl transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-semibold">{{ $device->type }} Device</h3>
                            <span class="px-2 py-1 text-xs rounded-full font-semibold
                                {{ $device->status === 'enable' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($device->status) }}
                            </span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-gray-600">Package:</span>
                                <span class="font-medium">{{ $device->package->name ?? 'Standard' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Duration:</span>
                                <span class="font-medium">{{ $device->sub_duration }} months</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Created:</span>
                                <span class="font-medium">{{ $device->created_at->format('M d, Y') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Expires:</span>
                                <span class="font-medium {{ $device->isExpiringSoon() ? 'text-red-600' : '' }}">
                                    {{ $device->expire_date->format('M d, Y') }}
                                    @if($device->isExpiringSoon())
                                        <span class="text-xs block text-red-600">Expiring Soon!</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t">
                            <a href="{{ route('devices.show', $device->id) }}" 
                               class="block text-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                                View Details
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $devices->links() }}
            </div>
        @else
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <p class="text-gray-500 text-lg">No devices found for this account.</p>
            </div>
        @endif
    </div>
</div>
@endsection
