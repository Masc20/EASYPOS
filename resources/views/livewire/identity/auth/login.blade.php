<div class="flex min-h-[70vh] flex-col items-center justify-center px-4">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-md border border-gray-100">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Sign in to EASYPOS</h1>
            <p class="mt-1 text-sm text-gray-500">Access Point of Sale terminal and back-office</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    required
                    autofocus
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="cashier@easypos.com"
                />
                @error('email')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    required
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="••••••••"
                />
                @error('password')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input
                        wire:model="remember"
                        type="checkbox"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="ml-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div>
                <button
                    type="submit"
                    class="flex w-full justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <span wire:loading.remove wire:target="login">Sign In</span>
                    <span wire:loading wire:target="login">Signing In...</span>
                </button>
            </div>
        </form>

        <div class="mt-6 border-t border-gray-100 pt-4 text-xs text-gray-500">
            <p class="font-medium text-gray-700 mb-1">Development test credentials:</p>
            <div class="space-y-1">
                <p><span class="font-semibold text-gray-600">Admin:</span> admin@easypos.com / password</p>
                <p><span class="font-semibold text-gray-600">Cashier:</span> cashier@easypos.com / password</p>
                <p><span class="font-semibold text-gray-600">Manager:</span> manager@easypos.com / password</p>
            </div>
        </div>
    </div>
</div>
