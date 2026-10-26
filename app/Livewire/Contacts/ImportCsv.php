<?php

namespace App\Livewire\Contacts;

use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Etiquette;
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
        // Ordre aligné sur un export Odoo (Nom, E-mail, Téléphone, Rue, Ville,
        // Pays…), puis les champs complémentaires, et les Étiquettes en dernier.
        return [
            'nom' => 'Nom / Raison sociale',
            'email' => 'E-mail',
            'telephone' => 'Téléphone',
            'adresse_rue' => 'Rue',
            'adresse_code_postal' => 'Code postal',
            'adresse_ville' => 'Ville',
            'adresse_pays' => 'Pays',
            'type' => 'Type (personne/entreprise)',
            'prenom' => 'Prénom',
            'fonction' => 'Fonction',
            'numero_entreprise' => "N° d'entreprise (BCE)",
            'numero_tva' => 'N° de TVA',
            'site_web' => 'Site web',
            'secteur' => 'Secteur',
            'url_profil' => 'Compte / lien',
            'categorie' => 'Catégorie (nom)',
            'temperature' => 'Température',
            'origine' => 'Origine du contact',
            'origine_precision' => 'Précisions sur l’origine',
            'discipline' => 'Discipline enseignée',
            'experience_yoga' => 'Expérience du yoga',
            'fait_observe' => 'Fait observé',
            'angle_contact' => 'Angle de premier contact',
            'notes' => 'Notes',
            'etiquettes' => 'Étiquettes (séparées par , ou |)',
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
            $e === 'type' || str_contains($e, 'type de') || str_contains($e, 'is_company') => 'type',
            str_contains($e, 'prénom') || str_contains($e, 'prenom') || str_contains($e, 'first') => 'prenom',
            str_contains($e, 'raison') || $e === 'nom' || str_contains($e, 'name') || str_contains($e, 'société') || str_contains($e, 'societe') || str_contains($e, 'display_name') => 'nom',
            str_contains($e, 'fonction') || str_contains($e, 'poste') || str_contains($e, 'title') || str_contains($e, 'position') || str_contains($e, 'job') => 'fonction',
            str_contains($e, 'mail') => 'email',
            str_contains($e, 'mobile') || str_contains($e, 'tel') || str_contains($e, 'phone') || str_contains($e, 'gsm') => 'telephone',
            str_contains($e, 'tva') || str_contains($e, 'vat') => 'numero_tva',
            str_contains($e, 'bce') || str_contains($e, 'kbo') || str_contains($e, 'company_registry') || str_contains($e, "n° d'entreprise") => 'numero_entreprise',
            str_contains($e, 'profil') || str_contains($e, 'compte') => 'url_profil',
            str_contains($e, 'web') || str_contains($e, 'site') => 'site_web',
            str_contains($e, 'secteur') || str_contains($e, 'industry') => 'secteur',
            str_contains($e, 'rue') || str_contains($e, 'street') || str_contains($e, 'adresse') => 'adresse_rue',
            str_contains($e, 'postal') || $e === 'cp' || str_contains($e, 'zip') => 'adresse_code_postal',
            str_contains($e, 'ville') || str_contains($e, 'city') => 'adresse_ville',
            str_contains($e, 'pays') || str_contains($e, 'country') => 'adresse_pays',
            str_contains($e, 'précision') || str_contains($e, 'precision') => 'origine_precision',
            str_contains($e, 'origine') || str_contains($e, 'origin') || $e === 'source' => 'origine',
            str_contains($e, 'températ') || str_contains($e, 'temperat') => 'temperature',
            str_contains($e, 'discipline') => 'discipline',
            str_contains($e, 'expérience') || str_contains($e, 'experience') || str_contains($e, 'niveau') => 'experience_yoga',
            str_contains($e, 'observ') => 'fait_observe',
            str_contains($e, 'angle') => 'angle_contact',
            str_contains($e, 'catégor') || str_contains($e, 'categor') => 'categorie',
            str_contains($e, 'étiquet') || str_contains($e, 'etiquet') || str_contains($e, 'tag') => 'etiquettes',
            str_contains($e, 'note') || str_contains($e, 'comment') => 'notes',
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
        // Odoo exporte is_company = True/1 pour une société.
        $estEntreprise = str_contains($type, 'entrep') || str_contains($type, 'company')
            || str_contains($type, 'société') || str_contains($type, 'societe')
            || $type === 'true' || $type === '1' || $type === 'vrai' || $type === 'oui';

        $donnees['type'] = $estEntreprise ? Contact::TYPE_ENTREPRISE : Contact::TYPE_PERSONNE;

        return $donnees;
    }

    /**
     * Normalise les valeurs à liste fermée (température, origine, expérience) :
     * accepte la clé OU le libellé (ex. « Instagram », « Professeurs de yoga »)
     * et stocke la clé interne ; une valeur inconnue est simplement ignorée.
     */
    protected function normaliserValeurs(array $donnees): array
    {
        $donnees = $this->normaliserType($donnees);

        $listes = [
            'temperature' => $this->libellesTemperatures(),
            'origine' => Contact::ORIGINES,
            'experience_yoga' => Contact::EXPERIENCES_YOGA,
        ];

        foreach ($listes as $champ => $map) {
            if (isset($donnees[$champ])) {
                $cle = $this->cleCorrespondante($donnees[$champ], $map);
                if ($cle === null) {
                    unset($donnees[$champ]); // valeur hors liste : on n'enregistre rien
                } else {
                    $donnees[$champ] = $cle;
                }
            }
        }

        return $donnees;
    }

    /** Clé d'une liste dont la valeur correspond à la clé ou au libellé (sinon null). */
    protected function cleCorrespondante(string $valeur, array $map): ?string
    {
        $v = mb_strtolower(trim($valeur));
        if ($v === '') {
            return null;
        }

        foreach ($map as $cle => $libelle) {
            if ($v === mb_strtolower((string) $cle) || $v === mb_strtolower((string) $libelle)) {
                return (string) $cle;
            }
        }

        return null;
    }

    /** Map clé => libellé des températures. @return array<string, string> */
    protected function libellesTemperatures(): array
    {
        return array_map(fn ($t) => $t[0], Contact::TEMPERATURES);
    }

    /**
     * Sépare les champs relationnels (catégorie par nom, étiquettes) des
     * colonnes directes, et renvoie [colonnes, nom catégorie, noms étiquettes].
     *
     * @return array{0: array<string, string>, 1: ?string, 2: list<string>}
     */
    protected function extraireRelations(array $donnees): array
    {
        $categorieNom = $donnees['categorie'] ?? null;
        unset($donnees['categorie']);

        $etiquettes = [];
        if (filled($donnees['etiquettes'] ?? null)) {
            foreach (preg_split('/[;,|]/', (string) $donnees['etiquettes']) as $nom) {
                $nom = trim($nom);
                if ($nom !== '') {
                    $etiquettes[] = $nom;
                }
            }
        }
        unset($donnees['etiquettes']);

        return [$donnees, filled($categorieNom) ? trim((string) $categorieNom) : null, $etiquettes];
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
            $donnees = $this->normaliserValeurs($this->mapperLigne($ligne));
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
                $donnees = $this->normaliserValeurs($this->mapperLigne($ligne));
                $validation = Validator::make($donnees, $this->reglesLigne());

                if ($validation->fails()) {
                    $ignorees[] = [
                        'ligne' => $numero + 2,
                        'messages' => $validation->errors()->all(),
                    ];

                    continue;
                }

                [$colonnes, $categorieNom, $etiquettes] = $this->extraireRelations($donnees);

                // Catégorie par nom (correspondance insensible à la casse, sinon ignorée).
                if ($categorieNom !== null) {
                    $cat = Categorie::whereRaw('LOWER(nom) = ?', [mb_strtolower($categorieNom)])->first();
                    if ($cat) {
                        $colonnes['categorie_id'] = $cat->id;
                    }
                }

                $contact = Contact::create($colonnes);

                // Étiquettes : créées si besoin, puis rattachées.
                if ($etiquettes !== []) {
                    $ids = [];
                    foreach ($etiquettes as $nom) {
                        $ids[] = Etiquette::firstOrCreate(['nom' => $nom])->id;
                    }
                    $contact->etiquettes()->sync($ids);
                }

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

    /** Télécharge un modèle CSV pré-rempli (en-têtes + 2 lignes d'exemple). */
    public function telechargerModele()
    {
        $colonnes = array_keys($this->champsDisponibles());

        $exemples = [
            [
                'type' => 'personne', 'nom' => 'Dubois', 'prenom' => 'Marie',
                'fonction' => 'Professeure de yoga', 'email' => 'marie.dubois@exemple.be',
                'telephone' => '+32 475 11 22 33', 'site_web' => 'https://exemple.be',
                'secteur' => 'Bien-être', 'adresse_rue' => 'Rue des Fleurs 10',
                'adresse_code_postal' => '5000', 'adresse_ville' => 'Namur', 'adresse_pays' => 'BE',
                'notes' => 'Rencontrée au salon', 'temperature' => 'Intérêt élevé',
                'origine' => 'Salon ou événement', 'origine_precision' => 'Salon Zen Namur 2026',
                'discipline' => 'Yoga nidra', 'experience_yoga' => 'Professeurs de yoga',
                'fait_observe' => 'Enseigne le nidra ; propose des ateliers.',
                'angle_contact' => 'Explorer le prana en complément du nidra.',
                'url_profil' => 'instagram.com/marie.yoga', 'categorie' => 'Formation profs de yoga',
                'etiquettes' => 'Prospect|Intéressé formation profs',
            ],
            [
                'type' => 'entreprise', 'nom' => 'Studio Zen SRL', 'email' => 'contact@studiozen.be',
                'telephone' => '+32 81 22 33 44', 'numero_entreprise' => '0403.170.701',
                'numero_tva' => 'BE0403.170.701', 'site_web' => 'https://studiozen.be',
                'secteur' => 'Yoga', 'adresse_rue' => 'Avenue du Calme 5',
                'adresse_code_postal' => '1000', 'adresse_ville' => 'Bruxelles', 'adresse_pays' => 'BE',
                'origine' => 'Recommandation', 'etiquettes' => 'Partenaire',
            ],
        ];

        $ligne = fn (array $valeurs): string => implode(';', array_map(
            fn ($col) => $valeurs[$col] ?? '',
            $colonnes
        ));

        $contenu = "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
        $contenu .= implode(';', $colonnes)."\r\n";
        foreach ($exemples as $ex) {
            $contenu .= $ligne($ex)."\r\n";
        }

        return response()->streamDownload(
            fn () => print ($contenu),
            'modele-import-contacts.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render(): View
    {
        return view('livewire.contacts.import-csv', [
            'champs' => $this->champsDisponibles(),
            'apercu' => $this->etape === 'mapping' ? $this->apercu() : null,
        ]);
    }
}
