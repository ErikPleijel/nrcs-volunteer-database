<x-layouts.app title="NRCS Login">
    @php
        $inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500';
        $buttonClass = 'w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition duration-300';
    @endphp
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-2 text-center">Login</h2>
            <p class="text-sm text-gray-600 mb-6 text-center">
                More than one account uses the phone number ending in <strong>{{ $phoneTail }}</strong>.
                Let's find yours.
            </p>

            @error('phone_login')
                <div class="mb-4 p-3 rounded-md bg-red-50 text-sm text-red-700">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login.phone') }}">
                @csrf

                @switch($step)
                    @case('db_number')
                        <div class="mb-6">
                            <label for="db_number" class="block text-sm font-medium text-gray-700 mb-2">DB number</label>
                            <p class="text-sm text-gray-600 mb-2">
                                If you have your DB number (it's on your ID card, e.g. DB-123456), enter it here.
                                Otherwise, skip this step.
                            </p>
                            <input id="db_number" type="text" name="db_number" autofocus autocomplete="off"
                                   placeholder="DB-123456" class="{{ $inputClass }}">
                        </div>
                        <div class="space-y-3">
                            <button type="submit" name="action" value="continue" class="{{ $buttonClass }}">Continue</button>
                            <button type="submit" name="action" value="skip"
                                    class="w-full py-2 px-4 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                I don't know it &mdash; skip
                            </button>
                        </div>
                        @break

                    @case('name')
                        <p class="text-sm text-gray-600 mb-4">Enter your first name and last name as you registered them.</p>
                        <div class="mb-4">
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First name</label>
                            <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                                   autocomplete="given-name" class="{{ $inputClass }} @error('first_name') border-red-500 @enderror">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-6">
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last name</label>
                            <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                                   autocomplete="family-name" class="{{ $inputClass }} @error('last_name') border-red-500 @enderror">
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="{{ $buttonClass }}">Continue</button>
                        @break

                    @case('birth_year')
                        <div class="mb-6">
                            <label for="birth_year" class="block text-sm font-medium text-gray-700 mb-2">Year of birth</label>
                            <p class="text-sm text-gray-600 mb-2">More than one account matches that name. What year were you born?</p>
                            <input id="birth_year" type="text" name="birth_year" value="{{ old('birth_year') }}" required autofocus
                                   inputmode="numeric" maxlength="4" placeholder="e.g. 1995"
                                   class="{{ $inputClass }} @error('birth_year') border-red-500 @enderror">
                            @error('birth_year')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="{{ $buttonClass }}">Continue</button>
                        @break

                    @case('pick')
                        <fieldset class="mb-6">
                            <legend class="text-sm text-gray-600 mb-3">We still found more than one matching account. Choose yours:</legend>
                            <div class="space-y-2">
                                @foreach($choices as $choice)
                                    <label class="flex items-center gap-3 p-3 border border-gray-300 rounded-md cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="account" value="{{ $choice->id }}" required
                                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
                                        <span>{{ $choice->first_name }} &mdash; DB-{{ $choice->formatUserIdForDisplay() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('account')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </fieldset>
                        <button type="submit" class="{{ $buttonClass }}">Continue</button>
                        @break

                    @case('password')
                        <div class="mb-6">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <p class="text-sm text-gray-600 mb-2">We found your account. Enter your password to log in.</p>
                            <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                                   class="{{ $inputClass }} @error('password') border-red-500 @enderror">
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="{{ $buttonClass }}">Login</button>
                        @break
                @endswitch
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-red-600 hover:text-red-800">Start over</a>
            </div>
        </div>
    </div>
</x-layouts.app>
