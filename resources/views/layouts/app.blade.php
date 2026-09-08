<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laurence B.') }}</title>

        {{-- Applique le thème enregistré avant le rendu (évite le clignotement) --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    if (t) document.documentElement.setAttribute('data-theme', t);
                } catch (e) {}
            })();
        </script>

        {{-- Polices : Cormorant Garamond (titres) + Figtree (corps) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="lb-shell">
            {{-- Barre latérale (logo unique en tête) --}}
            <aside class="lb-side">
                <a href="{{ route('dashboard') }}" class="lb-brand" wire:navigate>
                    <img src="{{ asset('images/prana-vidya-logo-epais-transparent.png') }}" alt="Logo Laurence B.">
                    <span>
                        <span class="name">Laurence B.</span>
                        <span class="sub" style="display:block">Carnet de relations</span>
                    </span>
                </a>

                <nav class="lb-nav">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" wire:navigate>
                        <svg viewBox="0 0 24 24"><path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
                        Accueil
                    </a>
                    <a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts.*') ? 'active' : '' }}" wire:navigate>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
                        Contacts
                    </a>
                    <a href="{{ route('pipeline.index') }}" class="{{ request()->routeIs('pipeline.*') ? 'active' : '' }}" wire:navigate>
                        <svg viewBox="0 0 24 24"><path d="M4 5h16M4 12h10M4 19h6"/></svg>
                        Pipeline
                    </a>
                    <a href="{{ route('etiquettes.index') }}" class="{{ request()->routeIs('etiquettes.*') ? 'active' : '' }}" wire:navigate>
                        <svg viewBox="0 0 24 24"><path d="M20.6 13.4 12 22l-8-8V4h10z"/><circle cx="8.5" cy="8.5" r="1.2"/></svg>
                        Étiquettes
                    </a>
                </nav>

                <div class="lb-side-foot">
                    <button type="button" class="lb-btn lb-btn-ghost" onclick="window.lbToggleTheme()" style="justify-content:center">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        <span data-theme-label>Mode sombre</span>
                    </button>

                    <div class="lb-usercard">
                        <span class="lb-av lb-av-34 lb-av-gold">{{ strtoupper(substr(auth()->user()->name ?? 'L', 0, 1)) }}</span>
                        <div style="min-width:0;flex:1">
                            <div style="font-weight:600;font-size:13px" class="lb-truncate">{{ auth()->user()->name ?? '' }}</div>
                            <a href="{{ route('profile') }}" class="lb-muted" style="font-size:11.5px;text-decoration:none" wire:navigate>Mon compte</a>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="lb-iconbtn" title="Se déconnecter" aria-label="Se déconnecter">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Contenu de la page --}}
            <main class="lb-main">
                {{ $slot }}
            </main>
        </div>

        <script>
            window.lbToggleTheme = function () {
                var root = document.documentElement;
                var cur = root.getAttribute('data-theme');
                if (!cur) {
                    cur = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                var next = cur === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try { localStorage.setItem('theme', next); } catch (e) {}
                document.querySelectorAll('[data-theme-label]').forEach(function (el) {
                    el.textContent = next === 'dark' ? 'Mode clair' : 'Mode sombre';
                });
            };
            (function () {
                var root = document.documentElement;
                var cur = root.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.querySelectorAll('[data-theme-label]').forEach(function (el) {
                    el.textContent = cur === 'dark' ? 'Mode clair' : 'Mode sombre';
                });
            })();
        </script>
    </body>
</html>
