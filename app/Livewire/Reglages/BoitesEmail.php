<?php

namespace App\Livewire\Reglages;

use App\Models\BoiteEmail;
use App\Models\Pole;
use App\Services\Email\LecteurEmails;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Gestion des boîtes e-mail (brique 009) : ajout / modification / suppression,
 * activation, et test de connexion. Le mot de passe est chiffré et n'est jamais
 * réaffiché ; on ne le remplace que si un nouveau contenu est saisi.
 */
#[Layout('layouts.app')]
class BoitesEmail extends Component
{
    public bool $formOuvert = false;

    public ?int $boiteId = null;

    // Champs du formulaire
    public ?string $libelle = null;

    public string $fournisseur = BoiteEmail::FOURNISSEUR_GMAIL;

    public ?string $email = null;

    public string $mot_de_passe = '';

    public bool $motDePasseDefini = false;

    public ?string $pole_id = null;

    public bool $active = true;

    // Réglages « Autre » uniquement
    public ?string $imap_host = null;

    public ?int $imap_port = 993;

    public ?string $dossier_envoyes = null;

    public ?string $message = null;

    public ?string $erreur = null;

    /** Ouvre le formulaire pour une nouvelle boîte. */
    public function nouvelle(): void
    {
        $this->reset([
            'boiteId', 'libelle', 'email', 'mot_de_passe', 'motDePasseDefini',
            'pole_id', 'imap_host', 'dossier_envoyes', 'message', 'erreur',
        ]);
        $this->fournisseur = BoiteEmail::FOURNISSEUR_GMAIL;
        $this->imap_port = 993;
        $this->active = true;
        $this->formOuvert = true;
        $this->resetValidation();
    }

    /** Charge une boîte existante dans le formulaire. */
    public function modifier(int $id): void
    {
        $boite = BoiteEmail::findOrFail($id);

        $this->boiteId = $boite->id;
        $this->libelle = $boite->libelle;
        $this->fournisseur = $boite->fournisseur;
        $this->email = $boite->email;
        $this->mot_de_passe = '';
        $this->motDePasseDefini = filled($boite->secret());
        $this->pole_id = $boite->pole_id !== null ? (string) $boite->pole_id : null;
        $this->active = (bool) $boite->active;
        $this->imap_host = $boite->imap_host;
        $this->imap_port = $boite->imap_port ?: 993;
        $this->dossier_envoyes = $boite->dossier_envoyes;
        $this->message = null;
        $this->erreur = null;
        $this->formOuvert = true;
        $this->resetValidation();
    }

    public function annuler(): void
    {
        $this->formOuvert = false;
        $this->resetValidation();
    }

    public function enregistrer(): void
    {
        $estAutre = $this->fournisseur === BoiteEmail::FOURNISSEUR_AUTRE;

        $donnees = $this->validate([
            'libelle' => ['nullable', 'string', 'max:100'],
            'fournisseur' => ['required', Rule::in(array_keys(BoiteEmail::fournisseurs()))],
            'email' => ['required', 'email', 'max:255'],
            'mot_de_passe' => [$this->boiteId ? 'nullable' : 'required', 'string', 'max:255'],
            'pole_id' => ['nullable', 'exists:poles,id'],
            'active' => ['boolean'],
            'imap_host' => [$estAutre ? 'required' : 'nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'dossier_envoyes' => ['nullable', 'string', 'max:255'],
        ]);

        // En mode Gmail/Hostinger, les réglages techniques viennent des presets : on ne stocke rien.
        $valeurs = [
            'libelle' => filled($donnees['libelle']) ? $donnees['libelle'] : null,
            'fournisseur' => $donnees['fournisseur'],
            'email' => $donnees['email'],
            'pole_id' => $donnees['pole_id'] ?: null,
            'active' => (bool) $this->active,
            'imap_host' => $estAutre ? $donnees['imap_host'] : null,
            'imap_port' => $estAutre ? ($donnees['imap_port'] ?: 993) : 993,
            'dossier_envoyes' => $estAutre ? ($donnees['dossier_envoyes'] ?: null) : null,
        ];

        $boite = $this->boiteId ? BoiteEmail::findOrFail($this->boiteId) : new BoiteEmail;
        $boite->fill($valeurs);

        // Secret : on ne remplace que si un nouveau contenu a été saisi.
        if (filled($this->mot_de_passe)) {
            $boite->mot_de_passe = $this->mot_de_passe;
        }

        $boite->save();

        $this->formOuvert = false;
        $this->reset('mot_de_passe');
        $this->message = 'Boîte e-mail enregistrée.';
    }

    public function supprimer(int $id): void
    {
        BoiteEmail::whereKey($id)->delete();
        if ($this->boiteId === $id) {
            $this->formOuvert = false;
        }
        $this->message = 'Boîte e-mail supprimée. (Les e-mails déjà importés sont conservés.)';
    }

    public function basculerActive(int $id): void
    {
        $boite = BoiteEmail::findOrFail($id);
        $boite->update(['active' => ! $boite->active]);
    }

    /** Teste la connexion d'une boîte enregistrée. */
    public function tester(int $id): void
    {
        $this->reset('message', 'erreur');
        $boite = BoiteEmail::findOrFail($id);

        try {
            app(LecteurEmails::class)->tester($boite);
            $this->message = 'Connexion réussie pour « '.$boite->nomAffichage().' ».';
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.reglages.boites-email', [
            'boites' => BoiteEmail::with('pole')->orderBy('id')->get(),
            'poles' => Pole::orderBy('ordre')->orderBy('nom')->get(),
            'fournisseurs' => BoiteEmail::fournisseurs(),
        ]);
    }
}
