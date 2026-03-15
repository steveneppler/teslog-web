<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Teslog' }}</title>
    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-page text-text-primary antialiased">
    <div class="w-full max-w-md px-6">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold tracking-tight">Teslog</h1>
            <p class="mt-2 text-text-muted">Tesla Vehicle Data Logger</p>
        </div>
        {{ $slot }}
    </div>
</body>
</html>
