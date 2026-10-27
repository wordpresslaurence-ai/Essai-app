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

    {{-- Aperçu des candidats --}}
    <h3 class="lb-h3 mt-6" style="font-size:18px">Candidats ({{ $candidats->count() }})</h3>
    <div class="lb-people mt-3">
        @forelse ($candidats as $c)
            <div class="lb-prow" wire:key="cand-{{ $c->id }}">
                <div style="min-width:0;flex:1">
                    <div class="nm lb-truncate">{{ $c->nom }}</div>
                    <div class="mt lb-truncate">
                        @if ($c->origine) {{ $c->origine }} @endif
                        @if ($c->email) · {{ $c->email }} @endif
                        @if ($c->champ('fonction')) · {{ $c->champ('fonction') }} @endif
                    </div>
                </div>
                @if ($c->url_profil)
                    <a href="{{ \Illuminate\Support\Str::startsWith($c->url_profil, ['http://','https://']) ? $c->url_profil : 'https://'.$c->url_profil }}"
                       target="_blank" rel="noopener noreferrer"
                       class="lb-accent" style="margin-left:auto;font-size:12.5px;font-weight:600;text-decoration:none">Profil ↗</a>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Aucun candidat pour l'instant. Colle le CSV ci-dessus et clique « Analyser ».</div>
        @endforelse
    </div>
</div>
