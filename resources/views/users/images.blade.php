<x-layouts.admin :title="'Photo & Signature: ' . ($user->full_name ?? 'Unknown User')">

    <style>
        @keyframes pulse-shadow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.5); }
            50%       { box-shadow: 0 0 0 18px rgba(99, 102, 241, 0); }
        }
        .btn-pulsing-shadow {
            animation: pulse-shadow 1.2s ease-in-out infinite;
        }
    </style>

    <x-slot name="pageHeader">
        <i class="fas fa-user mr-3"></i> Persons
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('users.show', $user) }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to Person
        </a>
    </x-slot>

    @can('update', $user)
        <x-slot name="button2">
            <a href="{{ route('users.edit', $user) }}" class="btn-edit">
                <i class="fas fa-edit mr-1"></i>Edit Person
            </a>
        </x-slot>
    @endcan

    <x-slot name="subHeader">
        Photo &amp; signature — {{ $user->full_name }}
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        @if (session('success'))
            <div class="max-w-4xl mx-auto bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="max-w-4xl mx-auto bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif
        @if (session('info'))
            <div class="max-w-4xl mx-auto bg-gray-100 border border-gray-300 text-gray-700 px-4 py-3 rounded mb-6">
                {{ session('info') }}
            </div>
        @endif

        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
            @include('users.partials.image-upload-cards')
        </div>
    </div>
</x-layouts.admin>
