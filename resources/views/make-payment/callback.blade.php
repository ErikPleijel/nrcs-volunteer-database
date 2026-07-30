<x-layouts.app title="Payment Status">
    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-receipt mr-3"></i> Payment Status
        </h1>
        <p class="mt-1 text-sm text-gray-600">
            Your Paystack payment
        </p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center">

                    @if (! $transaction)
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <div class="font-medium">
                                <i class="fas fa-circle-exclamation mr-1"></i> We could not find this payment
                            </div>
                            <p class="mt-1 text-sm">
                                If you believe this is an error, please contact your branch.
                            </p>
                        </div>
                    @elseif ($transaction->status === 'success')
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <div class="font-medium">
                                <i class="fas fa-circle-check mr-1"></i> Payment received
                            </div>
                            <p class="mt-1 text-sm">Thank you — your payment was successful and has been recorded.</p>
                        </div>
                        <a href="{{ route('profile.show') }}"
                           class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Back to My Profile
                        </a>
                    @elseif ($transaction->status === 'initiated')
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                            <div class="font-medium">
                                <i class="fas fa-clock mr-1"></i> Payment still being confirmed
                            </div>
                            <p class="mt-1 text-sm">
                                We're waiting for confirmation from Paystack. This can take a moment —
                                please check back shortly by reloading this page.
                            </p>
                        </div>
                        <a href="{{ route('profile.show') }}"
                           class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to My Profile
                        </a>
                    @else
                        {{-- 'failed' or 'abandoned' — both are terminal non-success outcomes. --}}
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <div class="font-medium">
                                <i class="fas fa-circle-exclamation mr-1"></i> Payment did not succeed
                            </div>
                            <p class="mt-1 text-sm">
                                Your payment was not completed. If you believe this is an error,
                                please contact your branch.
                            </p>
                        </div>
                        <a href="{{ route('make-payment.show') }}"
                           class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Try Again
                        </a>
                    @endif

                    @if($transaction && $transaction->reference)
                        <p class="mt-6 text-xs text-gray-400">Reference: {{ $transaction->reference }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
