<x-layouts.guest title="Setup - Teslog">
    <div class="mb-4 rounded-lg border border-blue-800 bg-blue-900/30 p-4 text-sm text-blue-300">
        Create the first user account to get started.
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        @if($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-900/50 p-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-text-secondary">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-text-secondary">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-text-secondary">Password</label>
            <input type="password" name="password" id="password" required
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-text-secondary">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-text-primary placeholder-text-subtle focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
        </div>

        <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-3 font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-page">
            Create Account
        </button>
    </form>
</x-layouts.guest>
