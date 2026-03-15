<x-layouts.guest title="Login - Teslog">
    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        @if($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-900/50 p-4 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <div>
            <label for="email" class="block text-sm font-medium text-text-secondary">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-text-secondary">Password</label>
            <input type="password" name="password" id="password" required
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="rounded border-border-strong bg-surface-alt text-red-500 focus:ring-red-500">
                <span class="text-sm text-text-muted">Remember me</span>
            </label>
        </div>

        <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-3 font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-page">
            Sign in
        </button>
    </form>
</x-layouts.guest>
