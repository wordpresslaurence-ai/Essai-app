<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Rules\NumeroEntrepriseBe;
use App\Rules\NumeroTvaBe;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ImportCsv extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $fichier = null;

    /** Étape courante : upload, mapping, termine. */
    public string $etape = 'upload';

    /** En-têtes détectés dans le CSV. @var array<int, string> */
    public array $entetes = [];

    /** Correspondance colonne CSV (index) -> champ contact. @var array<int, string> */
    public array $mapping = [];

    /** Rapport final après import. @var array<string, mixed> */
    public array $rapport = [];

    /** Champs du contact que l'on peut mapper. */
    public function champsDisponibles(): array
    {
        return [
            'type' => 'Type (personne/entreprise)',
            'nom' => 'Nom / Raison sociale',
            'prenom' => 'Prénom',
            'fonction' => 'Fonction',
            'email' => 'E-mail',
            'telephone' => 'Téléphone',
            'numero_entreprise' => "N° d'entreprise (BCE)",
            'numero_tva' => 'N° de TVA',
            'site_web' => 'Site web',
            'secteur' => 'Secteur',
            'adresse_rue' => 'Rue',
            'adresse_code_postal' => 'Code postal',
            'adresse_ville' => 'Ville',
            'adresse_pays' => 'Pays',
            'notes' => 'Notes',
        ];
    }

    /** À la sélection du fichier : on lit les en-têtes et on pré-remplit le mapping. */
    public function updatedFichier(): void
    {
        $this->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $lignes = $this->lireLignes();
        $this->entetes = $lignes[0] ?? [];

        // Devine la correspondance en comparant le nom de colonne aux champs.
        $this->mapping = [];
        foreach ($this->entetes as $i => $entete) {
            $this->mapping[$i] = $this->devinerChamp($entete);
        }

        $this->etape = 'mapping';
    }

    /** Devine le champ cible à partir du libellé de colonne. */
    protected function devinerChamp(string $entete): string
    {
        $e = mb_strtolower(trim($entete));

        return match (true) {
            $e === 'type' || str_contains($e, 'type de') => 'type',
            str_contains($e, 'raison') || $e === 'nom' || str_contains($e, 'name') || str_contains($e, 'société') || str_contains($e, 'societe') => 'nom',
            str_contains($e, 'prénom') || str_contains($e, 'prenom') || str_contains($e, 'first') => 'prenom',
            str_contains($e, 'fonction') || str_contains($e, 'poste') || str_contains($e, 'title') => 'fonction',
            str_contains($e, 'mail') => 'email',
            str_contains($e, 'tel') || str_contains($e, 'phone') || str_contains($e, 'gsm') => 'telephone',
            str_contains($e, 'tva') || str_contains($e, 'vat') => 'numero_tva',
            str_contains($e, 'bce') || str_contains($e, 'entreprise') || str_contains($e, 'kbo') => 'numero_entreprise',
            str_contains($e, 'web') || str_contains($e, 'site') => 'site_web',
            str_contains($e, 'secteur') => 'secteur',
            str_contains($e, 'rue') || str_contains($e, 'street') || str_contains($e, 'adresse') => 'adresse_rue',
            str_contains($e, 'postal') || str_contains($e, 'cp') || str_contains($e, 'zip') => 'adresse_code_postal',
            str_contains($e, 'ville') || str_contains($e, 'city') => 'adresse_ville',
            str_contains($e, 'pays') || str_contains($e, 'country') => 'adresse_pays',
            str_contains($e, 'note') => 'notes',
            default => '',
        };
    }

    /**
     * Lit toutes les lignes du CSV (en-tête inclus), en gérant le séparateur , ou ;.
     *
     * @return array<int, array<int, string>>
     */
    protected function lireLignes(): array
    {
        if (! $this->fichier) {
            return [];
        }

        $chemin = $this->fichier->getRealPath();
        $contenu = file_get_contents($chemin);

        // Détection simple du séparateur sur la première ligne.
        $premiereLigne = strtok($contenu, "\n");
        $separateur = substr_count($premiereLigne, ';') > substr_count($premiereLigne, ',') ? ';' : ',';

        $lignes = [];
        if (($h = fopen($chemin, 'r')) !== false) {
            while (($donnees = fgetcsv($h, 0, $separateur)) !== false) {
                $lignes[] = array_map(fn ($v) => trim((string) $v), $donnees);
            }
            fclose($h);
        }

        return $lignes;
    }

    /**
     * Transforme une ligne de données en tableau de champs selon le mapping.
     *
     * @param  array<int, string>  $ligne
     * @return array<string, string>
     */
    protected function mapperLigne(array $ligne): array
    {
        $donnees = [];
        foreach ($this->mapping as $i => $champ) {
            if ($champ !== '' && isset($ligne[$i]) && $ligne[$i] !== '') {
                $donnees[$champ] = $ligne[$i];
            }
        }

        return $donnees;
    }

    /** Normalise le type éventuel ('entreprise' si le libellé y ressemble). */
    protected function normaliserType(array $donnees): array
    {
        $type = mb_strtolower($donnees['type'] ?? '');
        $donnees['type'] = str_contains($type, 'entrep') || str_contains($type, 'company') || str_contains($type, 'société')
            ? Contact::TYPE_ENTREPRISE
            : Contact::TYPE_PERSONNE;

        return $donnees;
    }

    protected function reglesLigne(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'numero_entreprise' => ['nullable', new NumeroEntrepriseBe],
            'numero_tva' => ['nullable', new NumeroTvaBe],
        ];
    }

    /**
     * Prévisualisation : compte les lignes valides / en erreur / doublons (EF-19).
     *
     * @return array<string, mixed>
     */
    public function apercu(): array
    {
        $lignes = $this->lireLignes();
        $donneesLignes = array_slice($lignes, 1); // sans l'en-tête

        $valides = 0;
        $erreurs = [];
        $doublons = 0;
        $emailsVus = [];

        foreach ($donneesLignes as $numero => $ligne) {
            $donnees = $this->normaliserType($this->mapperLigne($ligne));
            $validation = Validator::make($donnees, $this->reglesLigne());

            if ($validation->fails()) {
                $erreurs[] = [
                    'ligne' => $numero + 2, // +1 en-tête, +1 index humain
                    'messages' => $validation->errors()->all(),
                ];

                continue;
            }

            $email = $donnees['email'] ?? null;
            if ($email && (in_array($email, $emailsVus, true) || Contact::where('email', $email)->exists())) {
                $doublons++;
            }
            if ($email) {
                $emailsVus[] = $email;
            }

            $valides++;
        }

        return [
            'total' => count($donneesLignes),
            'valides' => $valides,
            'doublons' => $doublons,
            'erreurs' => $erreurs,
        ];
    }

    /** Importe les lignes valides (EF-20), ignore et rapporte les invalides. */
    public function importer(): void
    {
        $lignes = $this->lireLignes();
        $donneesLignes = array_slice($lignes, 1);

        $importes = 0;
        $ignorees = [];

        DB::transaction(function () use ($donneesLignes, &$importes, &$ignorees) {
            foreach ($donneesLignes as $numero => $ligne) {
                $donnees = $this->normaliserType($this->mapperLigne($ligne));
                $validation = Validator::make($donnees, $this->reglesLigne());

                if ($validation->fails()) {
                    $ignorees[] = [
                        'ligne' => $numero + 2,
                        'messages' => $validation->errors()->all(),
                    ];

                    continue;
                }

                Contact::create($donnees);
                $importes++;
            }
        });

        $this->rapport = [
            'importes' => $importes,
            'ignorees' => $ignorees,
        ];
        $this->etape = 'termine';
    }

    public function recommencer(): void
    {
        $this->reset(['fichier', 'etape', 'entetes', 'mapping', 'rapport']);
        $this->etape = 'upload';
    }

    public function render(): View
    {
        return view('livewire.contacts.import-csv', [
            'champs' => $this->champsDisponibles(),
            'apercu' => $this->etape === 'mapping' ? $this->apercu() : null,
        ]);
    }
}
