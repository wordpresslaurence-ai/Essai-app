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

    {{-- Liste --}}
    <div class="lb-people mt-4">
        @forelse ($contacts as $c)
            @php
                $s = $c->statut_radar;
                $radar = $c->radarSite;
                $aJour = $radar && $radar->analyse_le && $radar->url === $c->site_web;
                $couleur = match ($s) {
                    \App\Models\RadarSite::A_AMELIORER, \App\Models\RadarSite::RESEAU_SEUL => 'background:var(--gold-tint);color:var(--accent)',
                    \App\Models\RadarSite::SANS_SITE, \App\Models\RadarSite::INJOIGNABLE => 'background:var(--terra-tint);color:var(--terra)',
                    \App\Models\RadarSite::CORRECT => 'background:var(--mint-tint);color:var(--mint-deep)',
                    default => 'background:var(--surface)',
                };
                $prospect = in_array($s, [\App\Models\RadarSite::A_AMELIORER, \App\Models\RadarSite::RESEAU_SEUL, \App\Models\RadarSite::SANS_SITE, \App\Models\RadarSite::INJOIGNABLE], true);
            @endphp
            <div class="lb-prow" wire:key="radar-{{ $c->id }}" style="flex-wrap:wrap;row-gap:10px;align-items:flex-start;{{ $radar?->ecarte_le ? 'opacity:.6' : '' }}">
                <span class="lb-tag" style="{{ $couleur }};flex:none">{{ \App\Models\RadarSite::STATUTS[$s] }}</span>

                <div style="min-width:12rem;flex:1">
                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate class="nm" style="text-decoration:none;color:inherit">{{ $c->nomComplet() }}</a>
                    <div class="mt" style="overflow-wrap:anywhere">
                        @if (filled($c->adresse_ville)){{ $c->adresse_ville }} · @endif
                        @if (filled($c->site_web))
                            <a href="{{ \App\Services\Radar\AnalyseurSite::normaliser($c->site_web) ?? '#' }}" target="_blank" rel="noopener noreferrer" class="lb-accent" style="font-weight:600">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#i', '', $c->site_web), 48) }}</a>
                        @elseif (filled($c->instagram) || filled($c->facebook))
                            présent sur {{ implode(' et ', array_filter([filled($c->instagram) ? 'Instagram' : null, filled($c->facebook) ? 'Facebook' : null])) }}
                        @else
                            aucune adresse web sur la fiche
                        @endif
                        @if ($aJour && $radar->techno) · fait avec {{ $radar->techno }}@endif
                    </div>
                    @if ($aJour && $radar->textesConstats())
                        <ul style="margin:6px 0 0;padding-left:18px;font-size:12.5px;color:var(--ink)">
                            @foreach ($radar->textesConstats() as $constat)
                                <li>{{ $constat }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($aJour)
                        <div class="lb-muted" style="font-size:11.5px;margin-top:4px">Visité le {{ $radar->analyse_le->format('d/m/Y') }}</div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                    @if (filled($c->site_web))
                        <button type="button" wire:click="analyser({{ $c->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px"
                                wire:loading.attr="disabled" wire:target="analyser({{ $c->id }})">
                            <span wire:loading.remove wire:target="analyser({{ $c->id }})">{{ $aJour ? 'Revisiter' : 'Analyser' }}</span>
                            <span wire:loading wire:target="analyser({{ $c->id }})">Visite…</span>
                        </button>
                    @endif
                    <button type="button" wire:click="basculerEcarte({{ $c->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">
                        {{ $radar?->ecarte_le ? 'Remettre' : 'Écarter' }}
                    </button>
                    @if ($prospect)
                        <a href="{{ route('contacts.fiche', array_filter(['contact' => $c, 'angle' => \App\Livewire\Prospection\Radar::angle($c, $s), 'offre' => $offreId ?: null])) }}#prospection"
                           class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">Préparer le mail</a>
                    @endif
                </div>
            </div>
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
