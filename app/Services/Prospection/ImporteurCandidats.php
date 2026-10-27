<?php

namespace App\Services\Prospection;

use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Etiquette;
use App\Models\RechercheProspection;
use Illuminate\Support\Facades\DB;

/**
 * Importe les candidats sélectionnés dans le carnet, de façon NON DESTRUCTIVE
 * et IDEMPOTENTE :
 * - nouveau contact → créé ;
 * - contact existant → complété (champs vides remplis, notes ajoutées à la suite,
 *   étiquettes fusionnées, e-mail ajouté en secondaire), jamais écrasé, opposition
 *   « ne plus contacter » préservée ;
 * - candidat déjà importé → ignoré (pas de doublon si on relance).
 */
class ImporteurCandidats
{
    public function __construct(
        private MappeurContact $mappeur,
        private Dedoublonnage $dedoublonnage,
    ) {}

    /**
     * @param  list<int|string>  $ids
     * @return array{crees:int, completes:int, ignores:int, lignes: list<array{nom:string, action:string, contact_id?:int}>}
     */
    public function importer(RechercheProspection $recherche, array $ids): array
    {
        $candidats = $recherche->candidats()->actifs()->whereIn('id', $ids)->get();
        $dedup = $this->dedoublonnage->pour($candidats);

        $crees = 0;
        $completes = 0;
        $ignores = 0;
        $lignes = [];

        // Évite de créer deux contacts identiques dans un même lot (doublon « liste »).
        $vusEmail = [];
        $vusUrl = [];

        DB::transaction(function () use ($candidats, $dedup, &$crees, &$completes, &$ignores, &$lignes, &$vusEmail, &$vusUrl) {
            foreach ($candidats as $c) {
                if ($c->estImporte()) {
                    $ignores++;
                    $lignes[] = ['nom' => (string) $c->nom, 'action' => 'déjà importé'];

                    continue;
                }

                $map = $this->mappeur->mapper($c->donnees ?? []);
                $colonnes = $map['colonnes'];

                if (blank($colonnes['nom'] ?? null)) {
                    $ignores++;
                    $lignes[] = ['nom' => (string) $c->nom, 'action' => 'ignoré (sans nom)'];

                    continue;
                }

                $email = filled($c->email) ? mb_strtolower(trim((string) $c->email)) : null;
                $url = Dedoublonnage::normaliserUrl($c->url_profil);

                // Contact cible : au carnet (dédoublonnage), ou déjà créé dans ce lot.
                $contact = null;
                if (! empty($dedup[$c->id]['contact_id'])) {
                    $contact = Contact::find($dedup[$c->id]['contact_id']);
                } elseif ($email && isset($vusEmail[$email])) {
                    $contact = $vusEmail[$email];
                } elseif ($url !== '' && isset($vusUrl[$url])) {
                    $contact = $vusUrl[$url];
                }

                if ($contact) {
                    $this->completer($contact, $colonnes, $map['etiquettes'], $email);
                    $completes++;
                    $action = 'complété';
                } else {
                    $contact = $this->creer($colonnes, $map['categorieNom'], $map['etiquettes']);
                    $crees++;
                    $action = 'créé';
                }

                if ($email) {
                    $vusEmail[$email] = $contact;
                }
                if ($url !== '') {
                    $vusUrl[$url] = $contact;
                }

                $c->update(['contact_id' => $contact->id, 'importe_at' => now()]);
                $lignes[] = ['nom' => $contact->nomComplet(), 'action' => $action, 'contact_id' => $contact->id];
            }
        });

        return ['crees' => $crees, 'completes' => $completes, 'ignores' => $ignores, 'lignes' => $lignes];
    }

    /** @param  array<string, mixed>  $colonnes */
    private function creer(array $colonnes, ?string $categorieNom, array $etiquettes): Contact
    {
        if ($categorieNom !== null) {
            $cat = Categorie::whereRaw('LOWER(nom) = ?', [mb_strtolower($categorieNom)])->first();
            if ($cat) {
                $colonnes['categorie_id'] = $cat->id;
            }
        }

        $contact = Contact::create($colonnes);
        $this->attacherEtiquettes($contact, $etiquettes, fusion: false);

        return $contact;
    }

    /** @param  array<string, mixed>  $colonnes */
    private function completer(Contact $contact, array $colonnes, array $etiquettes, ?string $email): void
    {
        foreach ($colonnes as $champ => $valeur) {
            if (in_array($champ, ['notes', 'type'], true)) {
                continue; // notes traitées à part ; on ne change pas le type d'une fiche existante
            }
            if ($champ === 'ne_plus_contacter') {
                // On ne fait qu'ajouter une opposition, jamais la retirer.
                if ($valeur === true && ! $contact->ne_plus_contacter) {
                    $contact->ne_plus_contacter = true;
                }

                continue;
            }
            // On ne remplit que les champs vides : jamais d'écrasement.
            if (blank($contact->{$champ}) && filled($valeur)) {
                $contact->{$champ} = $valeur;
            }
        }

        if (filled($colonnes['notes'] ?? null)) {
            $ajout = '[Prospection '.now()->format('d/m/Y').'] '.$colonnes['notes'];
            $contact->notes = trim(($contact->notes ? $contact->notes."\n\n" : '').$ajout);
        }

        $contact->save();

        $this->attacherEtiquettes($contact, $etiquettes, fusion: true);

        // E-mail du candidat ajouté en secondaire s'il diffère et n'existe pas déjà.
        if ($email && $email !== mb_strtolower((string) $contact->email)) {
            $existe = $contact->adressesEmail()->whereRaw('LOWER(adresse) = ?', [$email])->exists();
            if (! $existe) {
                $contact->adressesEmail()->create(['adresse' => $email]);
            }
        }
    }

    /** @param  list<string>  $noms */
    private function attacherEtiquettes(Contact $contact, array $noms, bool $fusion): void
    {
        if ($noms === []) {
            return;
        }

        $ids = [];
        foreach ($noms as $nom) {
            $ids[] = Etiquette::firstOrCreate(['nom' => $nom])->id;
        }

        $fusion
            ? $contact->etiquettes()->syncWithoutDetaching($ids)
            : $contact->etiquettes()->sync($ids);
    }
}
