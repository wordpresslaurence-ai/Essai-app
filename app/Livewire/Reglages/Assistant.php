<?php

namespace App\Livewire\Reglages;

use App\Models\Parametre;
use App\Services\Email\LecteurEmails;
use App\Services\Email\SynchronisationEmails;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Réglages de l'assistant : connexion e-mail, clé API Claude, liens Fluent Booking
 * et délai de relance. Les secrets ne sont jamais réaffichés en clair ; on ne les
 * remplace que si un nouveau contenu est saisi.
 */
#[Layout('layouts.app')]
class Assistant extends Component
{
    // Champs non secrets
    public ?string $gmail_email = null;

    public ?string $anthropic_modele = null;

    public int $relance_delai_jours = 5;

    public ?string $lien_appel_visio = null;

    public ?string $lien_seance_decouverte = null;

    public ?string $lien_journee_aura = null;

    // Secrets : champs de saisie (vides au chargement), remplacés uniquement si saisis
    public string $gmail_app_password = '';

    public string $anthropic_api_key = '';

    // Indique si un secret est déjà enregistré (pour l'affichage)
    public bool $gmailMotDePasseDefini = false;

    public bool $cleApiDefinie = false;

    // Résultat du dernier test de connexion (affiché dans la vue)
    public ?string $testMessage = null;

    public ?string $testErreur = null;

    public function mount(): void
    {
        $p = Parametre::instance();

        $this->gmail_email = $p->gmail_email;
        $this->anthropic_modele = $p->anthropic_modele;
        $this->relance_delai_jours = $p->relance_delai_jours ?? 5;

        // Pré-remplissage des liens (valeurs connues) si vides
        $this->lien_appel_visio = $p->lien_appel_visio
            ?? 'https://homeharmony.be/?fluent-booking=calendar&host=siteweb&event=appel-visio1';
        $this->lien_seance_decouverte = $p->lien_seance_decouverte
            ?? 'https://homeharmony.be/seance-prana-gratuite/';
        $this->lien_journee_aura = $p->lien_journee_aura
            ?? 'https://homeharmony.be/nettoyage-aura-chakras';

        $this->gmailMotDePasseDefini = filled($p->gmail_app_password);
        $this->cleApiDefinie = filled($p->anthropic_api_key);
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate([
            'gmail_email' => ['nullable', 'email', 'max:255'],
            'anthropic_modele' => ['nullable', 'string', 'max:100'],
            'relance_delai_jours' => ['required', 'integer', 'min:1', 'max:365'],
            'lien_appel_visio' => ['nullable', 'url', 'max:255'],
            'lien_seance_decouverte' => ['nullable', 'url', 'max:255'],
            'lien_journee_aura' => ['nullable', 'url', 'max:255'],
            'gmail_app_password' => ['nullable', 'string', 'max:255'],
            'anthropic_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $p = Parametre::instance();

        $p->gmail_email = $donnees['gmail_email'];
        $p->anthropic_modele = $donnees['anthropic_modele'];
        $p->relance_delai_jours = $donnees['relance_delai_jours'];
        $p->lien_appel_visio = $donnees['lien_appel_visio'];
        $p->lien_seance_decouverte = $donnees['lien_seance_decouverte'];
        $p->lien_journee_aura = $donnees['lien_journee_aura'];

        // Secrets : on ne remplace que si un nouveau contenu a été saisi
        if (filled($this->gmail_app_password)) {
            $p->gmail_app_password = $this->gmail_app_password;
        }
        if (filled($this->anthropic_api_key)) {
            $p->anthropic_api_key = $this->anthropic_api_key;
        }

        $p->save();

        // On vide les champs secrets et on rafraîchit les indicateurs
        $this->reset('gmail_app_password', 'anthropic_api_key');
        $this->gmailMotDePasseDefini = filled($p->gmail_app_password);
        $this->cleApiDefinie = filled($p->anthropic_api_key);

        session()->flash('message', 'Réglages enregistrés.');
    }

    /**
     * Teste la connexion à la boîte e-mail avec les réglages enregistrés.
     * Pense à enregistrer d'abord si tu viens de saisir le mot de passe.
     */
    public function testerConnexion(): void
    {
        $this->reset('testMessage', 'testErreur');

        try {
            app(LecteurEmails::class)->tester();
            $this->testMessage = 'Connexion à la boîte e-mail réussie.';
        } catch (Throwable $e) {
            $this->testErreur = $e->getMessage();
        }
    }

    /** Lance la synchronisation des e-mails avec la boîte configurée. */
    public function synchroniser(): void
    {
        $this->reset('testMessage', 'testErreur');

        try {
            $resultat = app(SynchronisationEmails::class)->executer();
            $this->testMessage = $resultat->resume();
        } catch (Throwable $e) {
            $this->testErreur = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.reglages.assistant');
    }
}
