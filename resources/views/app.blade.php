<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Open Graph and Social Media Meta Tags --}}
        @if(isset($shareData))
            <meta property="og:title" content="{{ $shareData['title'] }}">
            <meta property="og:description" content="{{ $shareData['description'] }}">
            <meta property="og:url" content="{{ $shareData['url'] }}">
            <meta property="og:type" content="{{ $shareData['type'] }}">
            <meta property="og:site_name" content="BagComics">
            @if($shareData['image'])
                <meta property="og:image" content="{{ $shareData['image'] }}">
                <meta property="og:image:width" content="400">
                <meta property="og:image:height" content="600">
                <meta property="og:image:type" content="image/jpeg">
                <meta property="og:image:alt" content="Cover of {{ $shareData['title'] }}">
            @endif
            
            {{-- Twitter Card Meta Tags --}}
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $shareData['title'] }}">
            <meta name="twitter:description" content="{{ $shareData['description'] }}">
            @if($shareData['image'])
                <meta name="twitter:image" content="{{ $shareData['image'] }}">
            @endif
            
            {{-- WhatsApp and general sharing --}}
            <meta property="og:locale" content="en_US">
        @else
            {{-- Default meta tags --}}
            <meta property="og:title" content="{{ config('app.name', 'BAG Comics') }} - Digital Comic Platform">
            <meta property="og:description" content="Discover, read, and enjoy amazing digital comics. Your ultimate comic reading platform.">
            <meta property="og:url" content="{{ url()->current() }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="BagComics">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ config('app.name', 'BAG Comics') }}">
            <meta name="twitter:description" content="Your ultimate digital comic reading platform.">
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'BAG Comics') }}</title>

        <!-- PWA Meta Tags -->
        <meta name="application-name" content="Comic Platform">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Comics">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="theme-color" content="#ef4444">
        <meta name="msapplication-TileColor" content="#ef4444">
        <meta name="msapplication-tap-highlight" content="no">

        <!-- Icons -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png">
        <link rel="apple-touch-icon" sizes="167x167" href="/apple-touch-icon-167x167.png">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead

    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- PWA Service Worker Registration --}}
        <script>
            // Unregister existing service workers for development
            if ('serviceWorker' in navigator && window.location.hostname === 'localhost') {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        registration.unregister();
                        console.log('ServiceWorker unregistered for development');
                    }
                });
            }
            
            // Only register ServiceWorker in production
            if ('serviceWorker' in navigator && window.location.hostname !== 'localhost' && window.location.protocol === 'https:') {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((registration) => {
                            console.log('SW registered: ', registration);
                            
                            // Check for updates
                            registration.addEventListener('updatefound', () => {
                                const newWorker = registration.installing;
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        // New content available, show update notification
                                        if (confirm('New version available! Reload to update?')) {
                                            window.location.reload();
                                        }
                                    }
                                });
                            });
                        })
                        .catch((registrationError) => {
                            console.log('SW registration failed: ', registrationError);
                        });
                });
            }

            // PWA Install Prompt
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Show install button or banner
                const installBanner = document.createElement('div');
                installBanner.innerHTML = `
                    <div style="position: fixed; bottom: 20px; left: 20px; right: 20px; background: #ef4444; color: white; padding: 1rem; border-radius: 0.5rem; z-index: 1000; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div>
                            <strong>Install BAG Comics</strong>
                            <p style="margin: 0; font-size: 0.875rem; opacity: 0.9;">Get the full app experience!</p>
                        </div>
                        <div>
                            <button id="install-btn" style="background: white; color: #ef4444; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; font-weight: 500; margin-right: 0.5rem; cursor: pointer;">Install</button>
                            <button id="dismiss-btn" style="background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer;">Dismiss</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(installBanner);
                
                document.getElementById('install-btn').addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        }
                        deferredPrompt = null;
                        installBanner.remove();
                    });
                });
                
                document.getElementById('dismiss-btn').addEventListener('click', () => {
                    installBanner.remove();
                });
            });

            // Handle app installed
            window.addEventListener('appinstalled', (evt) => {
                console.log('App was installed');
            });
        </script>

        {{-- Ensure Livewire CSRF token is set for admin area --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                if (window.Livewire) {
                    window.Livewire.setOptions({
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                }
            });
        </script>
    </body>
</html>
