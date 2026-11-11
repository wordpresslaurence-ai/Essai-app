<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière l'hébergeur (proxy HTTPS type Railway/Render/Hostinger),
        // faire confiance aux en-têtes de proxy pour générer des URL https correctes.
        $middleware->trustProxies(at: '*');

        // En-têtes de sécurité sur toutes les réponses web.
        $middleware->web(append: [\App\Http\Middleware\EntetesSecurite::class]);

        // Réception des réservations du site : appel de serveur à serveur, protégé
        // par un jeton secret dans l'adresse (pas de session, donc pas de jeton CSRF).
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
