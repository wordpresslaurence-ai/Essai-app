<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laurence B.') }}</title>

        <script>
            (function () { try { var t = localStorage.getItem('theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {} })();
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col justify-center items-center px-4" style="background:var(--bg)">
            <a href="/" wire:navigate class="flex flex-col items-center gap-3 mb-7" style="text-decoration:none">
                <img src="{{ asset('images/prana-vidya-logo-epais-transparent.png') }}" alt="Logo Laurence B." style="width:56px;height:84px;object-fit:contain;background:var(--logo-bg);border-radius:16px;padding:4px 8px;box-sizing:content-box">
                <span class="serif" style="font-size:26px;font-weight:600;color:var(--ink)">Laurence B.</span>
            </a>

            <div class="lb-card w-full sm:max-w-md" style="padding:28px">
                {{ $slot }}
            </div>

            <p class="lb-muted mt-6" style="font-size:11.5px;letter-spacing:.06em">Carnet de relations</p>
        </div>
    </body>
</html>
