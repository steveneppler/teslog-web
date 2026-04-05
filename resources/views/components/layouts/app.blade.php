<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Teslog' }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    @livewireStyles
</head>
<body class="h-full bg-page text-text-primary antialiased" x-data="{ sidebarOpen: false }">
    <!-- Mobile sidebar overlay -->
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[9998] bg-overlay lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <!-- Mobile sidebar -->
    <aside x-show="sidebarOpen" x-transition:enter="transition-transform duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition-transform duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-[9999] flex w-64 flex-col border-r border-border-default bg-surface lg:hidden" x-cloak>
            <div class="flex h-16 items-center justify-between border-b border-border-default px-6">
                <div class="flex items-center gap-2">
                    <img src="/images/logo.png" alt="Teslog" class="h-8 w-8">
                    <span class="text-xl font-bold tracking-tight">Teslog</span>
                </div>
                <button @click="sidebarOpen = false" class="text-text-muted hover:text-text-primary">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4">
                @include('components.layouts._nav-links')
            </nav>
    </aside>

    <div class="flex h-full">
        <!-- Desktop sidebar -->
        <aside class="hidden w-64 flex-shrink-0 border-r border-border-default bg-surface lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-2 border-b border-border-default px-6">
                <img src="/images/logo.png" alt="Teslog" class="h-8 w-8">
                <span class="text-xl font-bold tracking-tight">Teslog</span>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4">
                @include('components.layouts._nav-links')
            </nav>
        </aside>

        <!-- Main content -->
        <div class="relative z-0 flex flex-1 flex-col overflow-hidden">
            <!-- Top bar -->
            <header class="relative z-30 flex h-16 items-center justify-between border-b border-border-default bg-surface px-6">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden" @click="sidebarOpen = true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-lg font-semibold">{{ $header ?? 'Dashboard' }}</h1>
                </div>
                <div x-data="{
                    open: false,
                    theme: localStorage.getItem('theme') || '',
                    setTheme(t) {
                        this.theme = t;
                        localStorage.setItem('theme', t);
                        var d = document.documentElement;
                        d.classList.remove('dark', 'light');
                        if (t === 'dark') { d.classList.add('dark'); d.style.colorScheme = 'dark'; }
                        else if (t === 'light') { d.classList.add('light'); d.style.colorScheme = 'light'; }
                        else { d.style.colorScheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                        window.dispatchEvent(new Event('theme-changed'));
                        fetch('{{ route('settings.theme') }}', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ theme: t || null })
                        });
                    }
                }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-text-muted hover:bg-surface-alt hover:text-text-primary">
                        <span>{{ auth()->user()->name }}</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition x-cloak
                         class="absolute right-0 top-full z-50 mt-1 w-44 rounded-lg border border-border-default bg-surface py-1 shadow-xl">

                        {{-- Theme toggle --}}
                        <div class="px-3 py-2">
                            <p class="mb-1.5 text-xs font-medium text-text-subtle">Theme</p>
                            <div class="flex rounded-md border border-border-input">
                                <button @click="setTheme('')" title="Auto"
                                    class="flex flex-1 items-center justify-center rounded-l-md py-1.5"
                                    :class="theme === '' ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:text-text-secondary'">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </button>
                                <button @click="setTheme('light')" title="Light"
                                    class="flex flex-1 items-center justify-center border-x border-border-input py-1.5"
                                    :class="theme === 'light' ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:text-text-secondary'">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                </button>
                                <button @click="setTheme('dark')" title="Dark"
                                    class="flex flex-1 items-center justify-center rounded-r-md py-1.5"
                                    :class="theme === 'dark' ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:text-text-secondary'">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="my-1 border-t border-border-default"></div>

                        {{-- Sign out --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-muted hover:bg-surface-alt hover:text-text-primary">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    <script>
        // Seed localStorage from server-side theme preference
        @auth
            localStorage.setItem('theme', @json(auth()->user()->theme ?? ''));
        @endauth

        // Map tile URL helper for theme-aware maps
        window.getMapTileUrl = function() {
            var isDark = document.documentElement.classList.contains('dark') ||
                (!document.documentElement.classList.contains('light') &&
                 window.matchMedia('(prefers-color-scheme: dark)').matches);
            return isDark
                ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
        };

        // Map tile theme switching
        window.__leafletMaps = [];
        window.registerMap = function(map) {
            window.__leafletMaps.push(map);
        };
        window.addEventListener('theme-changed', function() {
            var url = window.getMapTileUrl();
            window.__leafletMaps = window.__leafletMaps.filter(function(map) {
                try { map.getContainer(); } catch(e) { return false; }
                return true;
            });
            window.__leafletMaps.forEach(function(map) {
                map.eachLayer(function(layer) {
                    if (layer instanceof L.TileLayer) {
                        layer.setUrl(url);
                    }
                });
            });
        });

        // Require Ctrl/Cmd to scroll-zoom maps and two fingers to interact on touch
        window.setupMapScrollZoom = function(map) {
            map.scrollWheelZoom.disable();
            var isTouch = 'ontouchstart' in window;
            var container = map.getContainer();

            map.dragging.disable();
            if (isTouch) {
                container.style.touchAction = 'pan-y';
            }

            var modKey = /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘' : 'Ctrl';
            var hintText = isTouch
                ? 'Use two fingers to move the map'
                : 'Hold ' + modKey + ' to move map';
            var ScrollHint = L.Control.extend({
                onAdd: function() {
                    var el = L.DomUtil.create('div', 'map-scroll-hint');
                    el.textContent = hintText;
                    return el;
                }
            });
            var hint = new ScrollHint({ position: 'bottomleft' }).addTo(map);
            var hintEl = hint.getContainer();
            var hideTimeout;
            function showHint() {
                hintEl.classList.add('visible');
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(function() { hintEl.classList.remove('visible'); }, 2500);
            }
            container.addEventListener('wheel', function(e) {
                if (e.ctrlKey || e.metaKey) return;
                showHint();
            }, { passive: true });
            container.addEventListener('mousedown', function(e) {
                if (!e.ctrlKey && !e.metaKey) showHint();
            });
            container.addEventListener('touchstart', function(e) {
                if (e.touches.length === 1) showHint();
                if (e.touches.length >= 2) hintEl.classList.remove('visible');
            }, { passive: true });
            container.addEventListener('mouseleave', function() {
                hintEl.classList.remove('visible');
                map.scrollWheelZoom.disable();
            });
            window.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    map.scrollWheelZoom.enable();
                    map.dragging.enable();
                    hintEl.classList.remove('visible');
                }
            });
            window.addEventListener('keyup', function() {
                map.scrollWheelZoom.disable();
                map.dragging.disable();
            });
        };

        // Chart.js theme helper
        window.getChartColors = function() {
            var style = getComputedStyle(document.documentElement);
            return {
                tick: style.getPropertyValue('--theme-text-muted').trim() || '#6b7280',
                grid: style.getPropertyValue('--theme-surface-alt').trim() || '#1f2937',
            };
        };
    </script>
</body>
</html>
