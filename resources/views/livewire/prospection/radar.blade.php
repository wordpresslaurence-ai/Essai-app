<div>
    <a href="{{ route('prospection.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la prospection</a>

    <div class="mt-4">
        <div class="lb-eyebrow">Prospection sites web</div>
        <h1 class="lb-h1">Radar sites web</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px;max-width:54rem">
            Parmi tes contacts, qui n'a pas de site, seulement un réseau social, ou un site à améliorer ?
            Le CRM visite la page d'accueil de chaque site et relève quelques indices simples.
            Ce sont des indices automatiques : jette un œil au site avant d'écrire.
        </p>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    {{-- Compteurs (cliquables : ils filtrent la liste) --}}
    <div class="flex flex-wrap items-center gap-2 mt-5">
        <button type="button" wire:click="$set('statut', '')" class="lb-pill" style="cursor:pointer;border:0;{{ $statut === '' ? 'color:var(--accent)' : '' }}">
            Tous · {{ $compteurs->sum() }}
        </button>
        @foreach (\App\Models\RadarSite::STATUTS as $cle => $libelle)
            <button type="button" wire:click="$set('statut', '{{ $cle }}')" class="lb-pill" style="cursor:pointer;border:0;{{ $statut === $cle ? 'color:var(--accent)' : '' }}">
                {{ $libelle }} · {{ $compteurs[$cle] ?? 0 }}
            </button>
        @endforeach
    </div>

    {{-- Filtres et actions --}}
    <div class="lb-card mt-4 flex flex-wrap items-end gap-3" style="padding:16px">
        <div style="flex:1;min-width:12rem">
            <label for="recherche" class="lb-label">Rechercher</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="recherche" class="lb-field" placeholder="Nom, e-mail…">
        </div>
        @if ($poles->isNotEmpty())
            <div style="min-width:12rem">
                <label for="categorie" class="lb-label">Catégorie des contacts</label>
                <select id="categorie" wire:model.live="categorie" class="lb-field">
                    <option value="">Toutes</option>
                    @foreach ($poles as $pole)
                        <optgroup label="{{ $pole->nom }}">
                            @foreach ($pole->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($offres->isNotEmpty())
            <div style="min-width:12rem">
                <label for="offreId" class="lb-label">Offre présentée dans le mail</label>
                <select id="offreId" wire:model.live="offreId" class="lb-field">
                    <option value="">— À choisir sur la fiche —</option>
                    @foreach ($offres as $offre)
                        <option value="{{ $offre->id }}">{{ $offre->libelleComplet() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="button" wire:click="analyserSuivants" class="lb-btn lb-btn-primary" wire:loading.attr="disabled" wire:target="analyserSuivants,analyser">
            <span wire:loading.remove wire:target="analyserSuivants">Analyser les {{ \App\Livewire\Prospection\Radar::LOT }} suivants</span>
            <span wire:loading wire:target="analyserSuivants">Visite des sites…</span>
        </button>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3" style="margin-top:8px">
        <p class="lb-muted" style="font-size:12px">
            {{ $compteurs[\App\Models\RadarSite::NON_ANALYSE] ?? 0 }} site(s) à analyser. Chaque visite prend quelques secondes, d'où les petits lots.
            @if ($offres->isEmpty()) Pour que le mail présente ton offre de sites web, décris-la dans Réglages → Assistant. @endif
        </p>
        <label class="flex items-center gap-2 lb-muted" style="font-size:12.5px;cursor:pointer">
            <input type="checkbox" wire:model.live="voirEcartes"> Montrer aussi les contacts écartés
        </label>
    </div>

    {{-- Résultats tout juste obtenus --}}
    @if ($recents->isNotEmpty())
        <div class="flex items-center justify-between gap-3 mt-5">
            <h3 class="lb-h3" style="font-size:17px">Tu viens d'analyser</h3>
            <button type="button" wire:click="oublierRecents" class="lb-muted" style="font-size:12.5px;background:none;border:0;cursor:pointer;text-decoration:underline">Masquer ce bloc</button>
        </div>
        <div class="lb-people mt-3">
            @foreach ($recents as $c)
                @include('livewire.prospection._radar-ligne')
            @endforeach
        </div>
        <h3 class="lb-h3 mt-6" style="font-size:17px">Tous les contacts</h3>
    @endif

    {{-- Liste --}}
    <div class="lb-people mt-4">
        @forelse ($contacts as $c)
            @include('livewire.prospection._radar-ligne')
        @empty
            <div class="lb-placeholder">Aucun contact dans cette liste.</div>
        @endforelse
    </div>

    @if ($total > $contacts->count())
        <div class="mt-4" style="text-align:center">
            <button type="button" wire:click="voirPlus" class="lb-btn lb-btn-ghost">Afficher la suite ({{ $total - $contacts->count() }} restants)</button>
        </div>
    @endif
</div>
