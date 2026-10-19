<?php

namespace App\Providers;

use App\Services\Email\ClientImap;
use App\Services\Email\LecteurEmails;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
