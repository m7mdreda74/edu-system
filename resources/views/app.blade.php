@php
    $allowedSiteThemes = ['royal', 'ocean', 'emerald', 'violet'];
    $siteTheme = data_get($page, 'props.settings.site_theme', 'royal');
    $siteTheme = in_array($siteTheme, $allowedSiteThemes, true) ? $siteTheme : 'royal';
    $cspNonce = request()->attributes->get('csp_nonce');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-site-theme="{{ $siteTheme }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Favicon & Brand Icons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-icon.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo-icon-192.png') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Unified Dark Mode Enforcer -->
        <script nonce="{{ $cspNonce }}">
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        </script>

        <!-- Fonts: keep the Arabic and Latin families consistent across all layouts. -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes(null, $cspNonce)
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-surface-950 text-white">
        @inertia
    </body>
</html>
