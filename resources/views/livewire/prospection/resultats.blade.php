<div>
    <a href="{{ route('prospection.modifier', $recherche) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour au brief</a>

    <h1 class="lb-h1 mt-4">Résultats — {{ $recherche->nom }}</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">
        Colle ici le CSV que ChatGPT t'a renvoyé. Les candidats sont rangés dans une zone de préparation,
        <strong>sans rien créer dans ton carnet</strong> : le tri et l'import viendront ensuite.
    </p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Dépôt du fichier (recommandé) --}}
    <div class="lb-card mt-5" style="padding:22px;max-width:860px">
        <label for="fichier" class="lb-label">Déposer le fichier .csv de ChatGPT <span class="lb-muted" style="font-weight:400">(recommandé — accents propres)</span></label>
        <input id="fichier" type="file" wire:model="fichier" accept=".csv,.txt" class="lb-field">
        @error('fichier') <p class="lb-error">{{ $message }}</p> @enderror
        <div wire:loading wire:target="fichier" class="lb-muted" style="font-size:12px;margin-top:6px">Chargement…</div>
        <div class="mt-3">
            <button type="button" wire:click="analyserFichier" class="lb-btn lb-btn-primary">Analyser le fichier</button>
        </div>

        <div class="lb-muted" style="font-size:12px;margin:16px 0 8px;text-align:center">— ou colle le contenu —</div>

        <label for="csv" class="lb-label">CSV collé</label>
        <textarea id="csv" rows="7" wire:model="csv" class="lb-field" style="font-family:ui-monospace,monospace;font-size:12.5px"
                  placeholder="nom;email;telephone;…&#10;Dupont;marie@exemple.be;…"></textarea>
        @error('csv') <p class="lb-error">{{ $message }}</p> @enderror
        <p class="lb-muted" style="font-size:11.5px;margin-top:4px">
            Le collage accepte le point-virgule, la virgule ou la tabulation. Si tu colles depuis un tableau et que
            les accents sont abîmés, préfère le dépôt du fichier ci-dessus.
        </p>

        <div class="flex items-center gap-3 mt-4">
            <button type="button" wire:click="analyser" class="lb-btn lb-btn-primary">Analyser le texte collé</button>
            @if ($candidats->isNotEmpty())
                <button type="button" wire:click="vider" wire:confirm="Effacer les candidats de cette recherche ?"
                        class="lb-btn lb-btn-ghost">Vider</button>
            @endif
        </div>

        @if ($rapport)
            <div class="lb-muted" style="font-size:12.5px;margin-top:10px">
                {{ $rapport['total'] }} candidat(s) retenu(s)@if ($rapport['ignores'] > 0), {{ $rapport['ignores'] }} ligne(s) ignorée(s) (sans nom)@endif.
                @if (!empty($rapport['entetes_inconnues']))
                    <br>Colonnes non reconnues (ignorées) : {{ implode(', ', $rapport['entetes_inconnues']) }}.
                @endif
            </div>
        @endif
    </div>

    {{-- Tableau de tri --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mt-7">
        <h3 class="lb-h3" style="font-size:18px">Candidats à trier</h3>
        <label class="flex items-center gap-2" style="font-size:12.5px;cursor:pointer">
            <input type="checkbox" wire:model.live="voirEcartes" style="width:15px;height:15px">
            Afficher les écartés ({{ $nbEcartes }})
        </label>
    </div>

    @if ($candidats->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3 mt-3">
            <label class="flex items-center gap-2" style="font-size:13px;cursor:pointer">
                <input type="checkbox" wire:click="basculerTout" style="width:16px;height:16px"
                       @checked(count($selection) >= $nbActifs && $nbActifs > 0)>
                Sélectionner les visibles
            </label>
            <span class="lb-muted" style="font-size:13px">{{ count($selection) }} sélectionné(s)</span>
            <button type="button" wire:click="preparerImport" @disabled(count($selection) === 0)
                    class="lb-btn lb-btn-primary" style="margin-left:auto;padding:8px 14px;font-size:13px;{{ count($selection) === 0 ? 'opacity:.5;cursor:not-allowed' : '' }}">
                Importer la sélection ({{ count($selection) }})
            </button>
        </div>
    @endif

    {{-- Rapport d'import --}}
    @if ($rapportImport)
        <div class="lb-alert lb-alert-success mt-4" style="font-size:13px">
            Import terminé : <strong>{{ $rapportImport['crees'] }}</strong> créé(s),
            <strong>{{ $rapportImport['completes'] }}</strong> complété(s)@if ($rapportImport['ignores']), {{ $rapportImport['ignores'] }} ignoré(s)@endif.
        </div>
    @endif

    <div class="lb-people mt-3">
        @forelse ($candidats as $c)
            @php($d = $doublons[$c->id] ?? ['type' => 'nouveau'])
            <div class="lb-prow" wire:key="cand-{{ $c->id }}" style="align-items:flex-start;flex-wrap:wrap;max-width:100%;{{ $c->ecarte ? 'opacity:.55' : '' }}">
                @if ($c->estImporte())
                    <span title="Importé" style="width:16px;flex:none;color:var(--mint-deep);line-height:1.5">✓</span>
                @elseif (! $c->ecarte)
                    <input type="checkbox" value="{{ $c->id }}" wire:model.live="selection" aria-label="Sélectionner" style="width:16px;height:16px;flex:none;margin-top:3px">
                @else
                    <span style="width:16px;flex:none"></span>
                @endif

                <div style="min-width:0;flex:1 1 220px">
                    <div class="nm lb-truncate">{{ $c->nom }}</div>

                    {{-- Badges sur leur propre ligne (se replient, toujours visibles) --}}
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:5px">
                        @if ($d['type'] === 'crm')
                            <a href="{{ route('contacts.fiche', $d['contact_id']) }}" wire:navigate class="lb-tag" style="background:var(--gold-tint);color:var(--accent);font-size:10.5px" title="Déjà dans le carnet : {{ $d['contact_nom'] ?? '' }}">Déjà dans le CRM</a>
                        @elseif ($d['type'] === 'liste')
                            <span class="lb-tag" style="background:var(--lav-tint,#efeafe);color:var(--lav);font-size:10.5px">Doublon potentiel</span>
                        @else
                            <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep);font-size:10.5px">Nouveau</span>
                        @endif
                        @if (!empty($d['deja_repere']))
                            <span class="lb-tag" style="background:var(--lav-tint,#efeafe);color:var(--lav);font-size:10.5px" title="Déjà repéré dans une autre recherche : « {{ $d['deja_repere'] }} »">↩ Déjà repéré (autre recherche)</span>
                        @endif
                        @if (!empty($d['adresse_partagee']))
                            <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent);font-size:10.5px" title="Même adresse que « {{ $d['adresse_partagee'] }} » — à relier éventuellement, pas forcément un doublon">⚑ Même adresse : {{ \Illuminate\Support\Str::limit($d['adresse_partagee'], 22) }}</span>
                        @endif
                        @if ($c->estImporte())<span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep);font-size:10.5px">Importé</span>@endif
                        @if ($c->ecarte)<span class="lb-tag" style="background:var(--surface);color:var(--text-muted);font-size:10.5px">Écarté</span>@endif
                    </div>

                    <div class="mt lb-truncate" style="margin-top:5px">
                        @if ($c->origine) {{ $c->origine }} @endif
                        @if ($c->champ('adresse_ville')) · {{ $c->champ('adresse_ville') }} @endif
                        @if ($c->email) · {{ $c->email }} @elseif ($c->champ('site_web')) · (site) @else · <span style="color:var(--terra)">aucun e-mail</span> @endif
                    </div>
                    @if ($c->champ('fait_observe'))
                        <div class="lb-muted lb-truncate" style="font-size:12px;margin-top:2px">{{ $c->champ('fait_observe') }}</div>
                    @endif
                </div>

                <div class="flex items-center gap-3" style="flex:none;flex-wrap:wrap">
                    <button type="button" wire:click="voirDetail({{ $c->id }})" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer">Détails</button>
                    @if ($c->estImporte() && $c->contact_id)
                        <a href="{{ route('contacts.fiche', $c->contact_id) }}" wire:navigate class="lb-muted" style="font-size:12px;text-decoration:none">Voir la fiche →</a>
                    @elseif ($c->ecarte)
                        <button type="button" wire:click="restaurer({{ $c->id }})" class="lb-muted" style="font-size:12px;background:none;border:0;cursor:pointer">Restaurer</button>
                    @else
                        <button type="button" wire:click="ecarter({{ $c->id }})" class="lb-muted" style="font-size:12px;background:none;border:0;cursor:pointer" title="Écarter pour cette recherche">Écarter</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucun candidat {{ $voirEcartes ? '' : 'actif ' }}pour l'instant. Dépose ou colle le CSV ci-dessus.</div>
        @endforelse
    </div>

    {{-- Récapitulatif avant import --}}
    @if ($confirmation)
        <div class="lb-modal-backdrop" x-data x-on:keydown.escape.window="$wire.annulerImport()">
            <div class="lb-modal" x-on:click.outside="$wire.annulerImport()">
                <h3 class="lb-h3" style="font-size:20px">Confirmer l'import</h3>
                <p class="lb-muted" style="font-size:13.5px;margin-top:10px">
                    <strong>{{ $apercuImport['total'] ?? 0 }}</strong> contact(s) sélectionné(s) :
                </p>
                <ul style="font-size:13.5px;margin-top:8px;line-height:1.7">
                    <li>➕ <strong>{{ $apercuImport['nouveaux'] ?? 0 }}</strong> nouveau(x) contact(s) créé(s)</li>
                    <li>✎ <strong>{{ $apercuImport['a_completer'] ?? 0 }}</strong> fiche(s) existante(s) à compléter (champs vides remplis, notes ajoutées, rien d'écrasé)</li>
                </ul>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="annulerImport" class="lb-btn lb-btn-ghost">Annuler</button>
                    <button type="button" wire:click="confirmerImport" class="lb-btn lb-btn-primary">Confirmer l'import de {{ $apercuImport['total'] ?? 0 }} contact(s)</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Panneau détail (40 champs) --}}
    @if ($detail)
        <div class="lb-modal-backdrop" x-data x-on:keydown.escape.window="$wire.fermerDetail()">
            <div class="lb-modal" style="max-width:640px;max-height:84vh;overflow:auto" x-on:click.outside="$wire.fermerDetail()">
                <div class="flex items-center justify-between">
                    <h3 class="lb-h3" style="font-size:19px">{{ $detail->nom }}</h3>
                    <button type="button" wire:click="fermerDetail" class="lb-iconbtn" aria-label="Fermer">✕</button>
                </div>
                <div class="lb-kv mt-4" style="grid-template-columns:1fr;gap:10px">
                    @foreach ($labels as $cle => $lib)
                        @if ($detail->champ($cle))
                            <div>
                                <div class="k">{{ $lib }}</div>
                                <div class="val" style="white-space:pre-wrap;word-break:break-word">{{ $detail->champ($cle) }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
