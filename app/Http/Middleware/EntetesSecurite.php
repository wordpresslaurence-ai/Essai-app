<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajoute des en-têtes HTTP de sécurité à chaque réponse web. Durcissement sans
 * effet fonctionnel : empêche le MIME-sniffing, l'inclusion de l'app dans une
 * iframe d'un autre site (clickjacking) et limite la fuite du référent.
 * (Pas de Content-Security-Policy ici : elle casserait Livewire/Alpine/Vite.)
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $reponse;
    }
}
