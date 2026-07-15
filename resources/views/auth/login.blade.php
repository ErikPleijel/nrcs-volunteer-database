<x-layouts.app title="NRCS Login">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-4">
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-2">Email or phone number</label>
                    <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus
                           autocomplete="username" placeholder="Email address or phone number"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 @error('login') border-red-500 @enderror">

                    @error('login')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required
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

                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input id="remember" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                        class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-900">Remember Me</label>
                    </div>

                    <a href="{{ route('password.request') }}" class="text-sm text-red-600 hover:text-red-800">Forgot your password?</a>
                </div>

                <div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition duration-300">
                        Login
                    </button>
                </div>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('register') }}" class="text-red-600 hover:text-red-800">Don't have an account? Register</a>
            </div>
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
