@php
    $allowedSiteThemes = ['royal', 'ocean', 'emerald', 'violet'];
    $siteTheme = data_get($page, 'props.settings.site_theme', 'royal');
    $siteTheme = in_array($siteTheme, $allowedSiteThemes, true) ? $siteTheme : 'royal';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-site-theme="{{ $siteTheme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Theme Detection script to prevent screen flash -->
        <script>
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        </script>

        <!-- Fonts: keep the Arabic and Latin families consistent across all layouts. -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
