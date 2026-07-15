<x-layouts.app title="Confirm Password">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-2 text-center">Confirm Password</h2>
            <p class="text-sm text-gray-600 mb-6 text-center">
                This is a secure area of the application. Please confirm your password before continuing.
            </p>

            <form method="POST" action="{{ route('password.confirm.store') }}">
                @csrf

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                               class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 @error('password') border-red-500 @enderror">
                        <button type="button" id="togglePassword"
                                aria-label="Show password"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition duration-300">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            const input = document.getElementById('password');
            const btn   = document.getElementById('togglePassword');
            const icon  = document.getElementById('togglePasswordIcon');
            if (!input || !btn) return;
            btn.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();
    </script>
</x-layouts.app>
