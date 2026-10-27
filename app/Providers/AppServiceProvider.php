<?php

namespace App\Providers;

use App\Services\Email\ClientImap;
use App\Services\Email\LecteurEmails;
use App\Services\IA\AssistantClaude;
use App\Services\IA\AssistantIA;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lecture des e-mails : client IMAP réel par défaut (les tests injectent un factice).
        $this->app->bind(LecteurEmails::class, ClientImap::class);

        // Assistant IA : client Claude réel par défaut (les tests injectent un factice).
        $this->app->bind(AssistantIA::class, AssistantClaude::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En production (HTTPS via l'hébergeur), on génère toujours des URL https.
        // Évite qu'une URL http générée fasse rediriger le POST de Livewire
        // (/livewire/update) en GET → erreur 405.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
